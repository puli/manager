<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Discovery;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Puli\Discovery\Api\Binding\MissingParameterException;
use Puli\Discovery\Api\Binding\NoSuchParameterException;
use Puli\Discovery\Api\EditableDiscovery;
use Puli\Discovery\Api\Validation\ConstraintViolation;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Discovery\DiscoveryNotEmptyException;
use Puli\Manager\Api\Discovery\DuplicateBindingException;
use Puli\Manager\Api\Discovery\DuplicateTypeException;
use Puli\Manager\Api\Discovery\NoSuchBindingException;
use Puli\Manager\Api\Discovery\NoSuchTypeException;
use Puli\Manager\Api\Discovery\TypeNotEnabledException;
use Puli\Manager\Api\Environment\ProjectEnvironment;
use Puli\Manager\Api\NonRootPackageExpectedException;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Assert\Assert;
use Puli\Manager\Discovery\Binding\AddBindingDescriptorToPackageFile;
use Puli\Manager\Discovery\Binding\Bind;
use Puli\Manager\Discovery\Binding\BindingDescriptorCollection;
use Puli\Manager\Discovery\Binding\DisableBindingUuid;
use Puli\Manager\Discovery\Binding\EnableBindingUuid;
use Puli\Manager\Discovery\Binding\LoadBindingDescriptor;
use Puli\Manager\Discovery\Binding\ReloadBindingDescriptorsByTypeName;
use Puli\Manager\Discovery\Binding\ReloadBindingDescriptorsByUuid;
use Puli\Manager\Discovery\Binding\RemoveBindingDescriptorFromPackageFile;
use Puli\Manager\Discovery\Binding\SyncBindingUuid;
use Puli\Manager\Discovery\Binding\UnloadBindingDescriptor;
use Puli\Manager\Discovery\Type\AddTypeDescriptorToPackageFile;
use Puli\Manager\Discovery\Type\BindingTypeDescriptorCollection;
use Puli\Manager\Discovery\Type\DefineType;
use Puli\Manager\Discovery\Type\LoadTypeDescriptor;
use Puli\Manager\Discovery\Type\RemoveTypeDescriptorFromPackageFile;
use Puli\Manager\Discovery\Type\SyncTypeName;
use Puli\Manager\Discovery\Type\UnloadTypeDescriptor;
use Puli\Manager\Discovery\Type\UpdateDuplicateMarksForTypeName;
use Puli\Manager\Package\PackageFileStorage;
use Puli\Manager\Transaction\InterceptedOperation;
use Puli\Manager\Transaction\Transaction;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DiscoveryManagerImpl implements DiscoveryManager
{
    /**
     * @var ProjectEnvironment
     */
    private $environment;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EditableDiscovery
     */
    private $discovery;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * @var RootPackage
     */
    private $rootPackage;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var BindingTypeDescriptorCollection
     */
    private $typeDescriptors;

    /**
     * @var BindingDescriptorCollection
     */
    private $bindingDescriptors;

    /**
     * Creates a tag manager.
     *
     * @param ProjectEnvironment $environment
     * @param EditableDiscovery  $discovery
     * @param PackageCollection  $packages
     * @param PackageFileStorage $packageFileStorage
     * @param LoggerInterface    $logger
     */
    public function __construct(
        ProjectEnvironment $environment,
        EditableDiscovery $discovery,
        PackageCollection $packages,
        PackageFileStorage $packageFileStorage,
        LoggerInterface $logger = null
    )
    {
        $this->environment = $environment;
        $this->discovery = $discovery;
        $this->packages = $packages;
        $this->packageFileStorage = $packageFileStorage;
        $this->rootPackage = $packages->getRootPackage();
        $this->rootPackageFile = $environment->getRootPackageFile();
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * {@inheritdoc}
     */
    public function addRootBindingType(BindingTypeDescriptor $typeDescriptor, $flags = 0)
    {
        Assert::integer($flags, 'The argument $flags must be a boolean.');

        $this->assertPackagesLoaded();
        $this->emitWarningForDuplicateTypes();

        if (!($flags & self::OVERRIDE) && $this->typeDescriptors->contains($typeDescriptor->getName())) {
            throw DuplicateTypeException::forTypeName($typeDescriptor->getName());
        }

        $tx = new Transaction();

        try {
            $typeName = $typeDescriptor->getName();
            $syncBindingOps = array();

            foreach ($this->getUuidsByTypeName($typeName) as $uuid) {
                $syncBindingOp = $this->syncBindingUuid($uuid);
                $syncBindingOp->takeSnapshot();
                $syncBindingOps[] = $syncBindingOp;
            }

            $syncOp = $this->syncTypeName($typeName);
            $syncOp->takeSnapshot();

            $tx->execute($this->loadTypeDescriptor($typeDescriptor, $this->rootPackage));
            $tx->execute($this->addTypeDescriptorToPackageFile($typeDescriptor));
            $tx->execute($syncOp);

            foreach ($syncBindingOps as $syncBindingOp) {
                $tx->execute($syncBindingOp);
            }

            $this->saveRootPackageFile();

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeRootBindingType($typeName)
    {
        // Only check that this is a string. The error message "not found" is
        // more helpful than e.g. "type name must contain /".
        Assert::string($typeName, 'The type name must be a string');

        $this->assertPackagesLoaded();

        if (!$this->typeDescriptors->contains($typeName, $this->rootPackage->getName())) {
            return;
        }

        $typeDescriptor = $this->typeDescriptors->get($typeName, $this->rootPackage->getName());
        $tx = new Transaction();

        try {
            $tx->execute($this->removeTypeDescriptorFromPackageFile($typeName));

            $syncBindingOps = array();

            foreach ($this->getUuidsByTypeName($typeName) as $uuid) {
                $syncBindingOp = $this->syncBindingUuid($uuid);
                $syncBindingOp->takeSnapshot();
                $syncBindingOps[] = $syncBindingOp;
            }

            $syncOp = $this->syncTypeName($typeName);
            $syncOp->takeSnapshot();

            $tx->execute($this->unloadTypeDescriptor($typeDescriptor));
            $tx->execute($syncOp);

            foreach ($syncBindingOps as $syncBindingOp) {
                $tx->execute($syncBindingOp);
            }

            $this->saveRootPackageFile();

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }

        $this->emitWarningForDuplicateTypes();
    }

    /**
     * {@inheritdoc}
     */
    public function clearRootBindingTypes()
    {
        $this->assertPackagesLoaded();

        $tx = new Transaction();
        $syncBindingOps = array();

        try {
            foreach ($this->getRootBindingTypes() as $typeDescriptor) {
                $typeName = $typeDescriptor->getName();

                $tx->execute($this->removeTypeDescriptorFromPackageFile($typeName));

                foreach ($this->getUuidsByTypeName($typeName) as $uuid) {
                    $syncBindingOp = $this->syncBindingUuid($uuid);
                    $syncBindingOp->takeSnapshot();
                    $syncBindingOps[] = $syncBindingOp;
                }

                $syncOp = $this->syncTypeName($typeName);
                $syncOp->takeSnapshot();

                $tx->execute($this->unloadTypeDescriptor($typeDescriptor));
                $tx->execute($syncOp);
            }

            foreach ($syncBindingOps as $syncBindingOp) {
                $tx->execute($syncBindingOp);
            }

            $this->saveRootPackageFile();

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }

        $this->emitWarningForDuplicateTypes();
    }

    /**
     * {@inheritdoc}
     */
    public function getRootBindingType($typeName)
    {
        return $this->getBindingType($typeName, $this->rootPackage->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function getRootBindingTypes()
    {
        $this->assertPackagesLoaded();

        $types = array();
        $rootPackageName = $this->rootPackage->getName();

        foreach ($this->typeDescriptors->toArray() as $typeName => $typesByPackage) {
            if (isset($typesByPackage[$rootPackageName])) {
                $types[] = $typesByPackage[$rootPackageName];
            }
        }

        return $types;
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootBindingType($typeName)
    {
        return $this->hasBindingType($typeName, $this->rootPackage->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootBindingTypes(Expression $expr = null)
    {
        $expr2 = Expr::same($this->rootPackage->getName(), BindingTypeDescriptor::CONTAINING_PACKAGE);

        if ($expr) {
            $expr2 = $expr2->andX($expr);
        }

        return $this->hasBindingTypes($expr2);
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingType($typeName, $packageName)
    {
        Assert::string($typeName, 'The type name must be a string. Got: %s');
        Assert::string($packageName, 'The package name must be a string. Got: %s');

        $this->assertPackagesLoaded();

        if (!$this->typeDescriptors->contains($typeName, $packageName)) {
            throw NoSuchTypeException::forTypeName($typeName);
        }

        return $this->typeDescriptors->get($typeName, $packageName);
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingTypes()
    {
        $this->assertPackagesLoaded();

        $types = array();

        foreach ($this->typeDescriptors->toArray() as $typeName => $typesByPackage) {
            foreach ($typesByPackage as $type) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * {@inheritdoc}
     */
    public function findBindingTypes(Expression $expr)
    {
        $this->assertPackagesLoaded();

        $types = array();

        foreach ($this->typeDescriptors->toArray() as $typeName => $typesByPackage) {
            foreach ($typesByPackage as $type) {
                if ($type->match($expr)) {
                    $types[] = $type;
                }
            }
        }

        return $types;
    }

    /**
     * {@inheritdoc}
     */
    public function hasBindingType($typeName, $packageName = null)
    {
        Assert::nullOrString($packageName, 'The package name must be a string or null. Got: %s');

        $this->assertPackagesLoaded();

        return $this->typeDescriptors->contains($typeName, $packageName);
    }

    /**
     * {@inheritdoc}
     */
    public function hasBindingTypes(Expression $expr = null)
    {
        $this->assertPackagesLoaded();

        if (!$expr) {
            return !$this->typeDescriptors->isEmpty();
        }

        foreach ($this->typeDescriptors->toArray() as $typeName => $typesByPackage) {
            foreach ($typesByPackage as $type) {
                if ($type->match($expr)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function addRootBinding(BindingDescriptor $bindingDescriptor, $flags = 0)
    {
        $this->assertPackagesLoaded();

        $typeName = $bindingDescriptor->getTypeName();
        $typeExists = $this->typeDescriptors->contains($typeName);

        if (!($flags & self::IGNORE_TYPE_NOT_FOUND) && !$typeExists) {
            throw NoSuchTypeException::forTypeName($typeName);
        }

        if (!($flags & self::IGNORE_TYPE_NOT_ENABLED) && $typeExists && !$this->typeDescriptors->getFirst($typeName)->isEnabled()) {
            throw TypeNotEnabledException::forTypeName($typeName);
        }

        $uuid = $bindingDescriptor->getUuid();

        if ($this->bindingDescriptors->contains($uuid)) {
            throw DuplicateBindingException::forUuid($uuid);
        }

        $tx = new Transaction();

        try {
            $syncOp = $this->syncBindingUuid($uuid);
            $syncOp->takeSnapshot();

            $tx->execute($this->loadBindingDescriptor($bindingDescriptor, $this->rootPackage));

            $this->assertBindingValid($bindingDescriptor);

            $tx->execute($this->addBindingDescriptorToPackageFile($bindingDescriptor));
            $tx->execute($syncOp);

            $this->saveRootPackageFile();

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeRootBinding(Uuid $uuid)
    {
        $this->assertPackagesLoaded();

        if (!$this->bindingDescriptors->contains($uuid)) {
            return;
        }

        $bindingDescriptor = $this->bindingDescriptors->get($uuid);

        if (!$bindingDescriptor->getContainingPackage() instanceof RootPackage) {
            return;
        }

        $tx = new Transaction();

        try {
            $syncOp = $this->syncBindingUuid($uuid);
            $syncOp->takeSnapshot();

            $tx->execute($this->unloadBindingDescriptor($bindingDescriptor));
            $tx->execute($syncOp);
            $tx->execute($this->removeBindingDescriptorFromPackageFile($uuid));

            $this->saveRootPackageFile();

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearRootBindings()
    {
        $this->assertPackagesLoaded();

        $tx = new Transaction();

        try {
            foreach ($this->getRootBindings() as $bindingDescriptor) {
                $syncOp = $this->syncBindingUuid($bindingDescriptor->getUuid());
                $syncOp->takeSnapshot();

                $tx->execute($this->unloadBindingDescriptor($bindingDescriptor));
                $tx->execute($syncOp);
                $tx->execute($this->removeBindingDescriptorFromPackageFile($bindingDescriptor->getUuid()));
            }

            $this->saveRootPackageFile();

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRootBinding(Uuid $uuid)
    {
        $binding = $this->getBinding($uuid);

        if (!$binding->getContainingPackage() instanceof RootPackage) {
            throw NoSuchBindingException::forUuidAndPackage($uuid, $this->rootPackage->getName());
        }

        return $binding;
    }

    /**
     * {@inheritdoc}
     */
    public function getRootBindings()
    {
        $this->assertPackagesLoaded();

        $bindings = array();

        foreach ($this->bindingDescriptors->toArray() as $binding) {
            if ($binding->getContainingPackage() instanceof RootPackage) {
                $bindings[] = $binding;
            }
        }

        return $bindings;
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootBinding(Uuid $uuid)
    {
        return $this->hasBinding($uuid) && $this->getBinding($uuid)->getContainingPackage() instanceof RootPackage;
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootBindings(Expression $expr = null)
    {
        $expr2 = Expr::same($this->rootPackage->getName(), BindingDescriptor::CONTAINING_PACKAGE);

        if ($expr) {
            $expr2 = $expr2->andX($expr);
        }

        return $this->hasBindings($expr2);
    }

    /**
     * {@inheritdoc}
     */
    public function enableBinding(Uuid $uuid)
    {
        $this->assertPackagesLoaded();

        if (!$this->bindingDescriptors->contains($uuid)) {
            throw NoSuchBindingException::forUuid($uuid);
        }

        $bindingDescriptor = $this->bindingDescriptors->get($uuid);
        $package = $bindingDescriptor->getContainingPackage();

        if ($package instanceof RootPackage) {
            throw NonRootPackageExpectedException::cannotEnableBinding($uuid, $package->getName());
        }

        if ($bindingDescriptor->isTypeNotFound()) {
            throw NoSuchTypeException::forTypeName($bindingDescriptor->getTypeName());
        }

        if ($bindingDescriptor->isTypeNotEnabled()) {
            throw TypeNotEnabledException::forTypeName($bindingDescriptor->getTypeName());
        }

        if ($bindingDescriptor->isEnabled()) {
            return;
        }

        $tx = new Transaction();

        try {
            $syncOp = $this->syncBindingUuid($uuid);
            $syncOp->takeSnapshot();

            $tx->execute($this->enableBindingUuid($uuid, $package->getInstallInfo()));
            $tx->execute($syncOp);

            $this->saveRootPackageFile();

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disableBinding(Uuid $uuid)
    {
        $this->assertPackagesLoaded();

        if (!$this->bindingDescriptors->contains($uuid)) {
            throw NoSuchBindingException::forUuid($uuid);
        }

        $bindingDescriptor = $this->bindingDescriptors->get($uuid);
        $package = $bindingDescriptor->getContainingPackage();

        if ($package instanceof RootPackage) {
            throw NonRootPackageExpectedException::cannotDisableBinding($uuid, $package->getName());
        }

        if ($bindingDescriptor->isTypeNotFound()) {
            throw NoSuchTypeException::forTypeName($bindingDescriptor->getTypeName());
        }

        if ($bindingDescriptor->isTypeNotEnabled()) {
            throw TypeNotEnabledException::forTypeName($bindingDescriptor->getTypeName());
        }

        if ($bindingDescriptor->isDisabled()) {
            return;
        }

        $tx = new Transaction();

        try {
            $syncOp = $this->syncBindingUuid($uuid);
            $syncOp->takeSnapshot();

            $tx->execute($this->disableBindingUuid($uuid, $package->getInstallInfo()));
            $tx->execute($syncOp);

            $this->saveRootPackageFile();

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getBinding(Uuid $uuid)
    {
        $this->assertPackagesLoaded();

        if (!$this->bindingDescriptors->contains($uuid)) {
            throw NoSuchBindingException::forUuid($uuid);
        }

        return $this->bindingDescriptors->get($uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function getBindings()
    {
        $this->assertPackagesLoaded();

        return array_values($this->bindingDescriptors->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function findBindings(Expression $expr)
    {
        $this->assertPackagesLoaded();

        $bindings = array();

        foreach ($this->bindingDescriptors->toArray() as $binding) {
            if ($binding->match($expr)) {
                $bindings[] = $binding;
            }
        }

        return $bindings;
    }

    /**
     * {@inheritdoc}
     */
    public function hasBinding(Uuid $uuid)
    {
        $this->assertPackagesLoaded();

        return $this->bindingDescriptors->contains($uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function hasBindings(Expression $expr = null)
    {
        $this->assertPackagesLoaded();

        if (!$expr) {
            return !$this->bindingDescriptors->isEmpty();
        }

        foreach ($this->bindingDescriptors->toArray() as $binding) {
            if ($binding->match($expr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function buildDiscovery()
    {
        $this->assertPackagesLoaded();
        $this->emitWarningForDuplicateTypes();
        $this->emitWarningForInvalidBindings();

        if (count($this->discovery->getBindings()) > 0 || count($this->discovery->getDefinedTypes()) > 0) {
            throw new DiscoveryNotEmptyException('The discovery is not empty.');
        }

        $tx = new Transaction();

        try {
            foreach ($this->typeDescriptors->toArray() as $typeName => $typesByPackage) {
                foreach ($typesByPackage as $typeDescriptor) {
                    if ($typeDescriptor->isEnabled()) {
                        $tx->execute($this->defineType($typeDescriptor));
                    }
                }
            }

            foreach ($this->bindingDescriptors->toArray() as $bindingDescriptor) {
                if ($bindingDescriptor->isEnabled()) {
                    $tx->execute($this->bind($bindingDescriptor));
                }
            }

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearDiscovery()
    {
        $this->discovery->clear();
    }

    private function assertPackagesLoaded()
    {
        if (!$this->typeDescriptors) {
            $this->loadPackages();
        }
    }

    private function assertBindingValid(BindingDescriptor $bindingDescriptor)
    {
        if ($bindingDescriptor->isTypeNotFound() || $bindingDescriptor->isTypeNotEnabled()) {
            return;
        }

        foreach ($bindingDescriptor->getViolations() as $violation) {
            switch ($violation->getCode()) {
                case ConstraintViolation::NO_SUCH_PARAMETER:
                    throw NoSuchParameterException::forParameterName($violation->getParameterName(), $violation->getTypeName());
                case ConstraintViolation::MISSING_PARAMETER:
                    throw MissingParameterException::forParameterName($violation->getParameterName(), $violation->getTypeName());
            }
        }
    }

    private function loadPackages()
    {
        $this->typeDescriptors = new BindingTypeDescriptorCollection();
        $this->bindingDescriptors = new BindingDescriptorCollection();

        // First load all the types
        foreach ($this->packages as $package) {
            foreach ($package->getPackageFile()->getTypeDescriptors() as $typeDescriptor) {
                $this->loadTypeDescriptor($typeDescriptor, $package)->execute();
            }
        }

        // Then the bindings for the loaded types
        foreach ($this->packages as $package) {
            foreach ($package->getPackageFile()->getBindingDescriptors() as $bindingDescriptor) {
                // This REALLY shouldn't happen
                if ($this->bindingDescriptors->contains($bindingDescriptor->getUuid())) {
                    throw DuplicateBindingException::forUuid($bindingDescriptor->getUuid());
                }

                $this->loadBindingDescriptor($bindingDescriptor, $package)->execute();
            }
        }
    }

    private function emitWarningForDuplicateTypes()
    {
        foreach ($this->typeDescriptors->getTypeNames() as $typeName) {
            $packageNames = $this->typeDescriptors->getPackageNames($typeName);

            if (count($packageNames) > 1) {
                $lastPackageName = array_pop($packageNames);

                $this->logger->warning(sprintf(
                    'The packages "%s" and "%s" contain type definitions for '.
                    'the same type "%s". The type has been disabled.',
                    implode('", "', $packageNames),
                    $lastPackageName,
                    $typeName
                ));
            }
        }
    }

    private function emitWarningForInvalidBindings()
    {
        foreach ($this->bindingDescriptors->toArray() as $binding) {
            foreach ($binding->getViolations() as $violation) {
                switch ($violation->getCode()) {
                    case ConstraintViolation::NO_SUCH_PARAMETER:
                        $reason = sprintf(
                            'The parameter "%s" does not exist.',
                            $violation->getParameterName()
                        );
                        break;
                    case ConstraintViolation::MISSING_PARAMETER:
                        $reason = sprintf(
                            'The parameter "%s" is missing.',
                            $violation->getParameterName()
                        );
                        break;
                    default:
                        $reason = 'Unknown reason.';
                        break;
                }

                $this->logger->warning(sprintf(
                    'The binding "%s" in package "%s" is invalid: %s',
                    $binding->getUuid()->toString(),
                    $binding->getContainingPackage()->getName(),
                    $reason
                ));
            }
        }
    }

    private function getUuidsByTypeName($typeName)
    {
        $uuids = array();

        foreach ($this->bindingDescriptors->getUuids() as $uuid) {
            if ($typeName === $this->bindingDescriptors->get($uuid)->getTypeName()) {
                $uuids[$uuid->toString()] = $uuid;
            }
        }

        return $uuids;
    }

    private function saveRootPackageFile()
    {
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
    }

    private function addTypeDescriptorToPackageFile(BindingTypeDescriptor $typeDescriptor)
    {
        return new AddTypeDescriptorToPackageFile($typeDescriptor, $this->rootPackageFile);
    }

    private function removeTypeDescriptorFromPackageFile($typeName)
    {
        return new RemoveTypeDescriptorFromPackageFile($typeName, $this->rootPackageFile);
    }

    private function loadTypeDescriptor(BindingTypeDescriptor $typeDescriptor, Package $package)
    {
        $typeName = $typeDescriptor->getName();

        return new InterceptedOperation(
            new LoadTypeDescriptor($typeDescriptor, $package, $this->typeDescriptors),
            array(
                new UpdateDuplicateMarksForTypeName($typeName, $this->typeDescriptors),
                new ReloadBindingDescriptorsByTypeName($typeName, $this->bindingDescriptors, $this->typeDescriptors)
            )
        );
    }

    private function unloadTypeDescriptor(BindingTypeDescriptor $typeDescriptor)
    {
        $typeName = $typeDescriptor->getName();

        return new InterceptedOperation(
            new UnloadTypeDescriptor($typeDescriptor, $this->typeDescriptors),
            array(
                new UpdateDuplicateMarksForTypeName($typeName, $this->typeDescriptors),
                new ReloadBindingDescriptorsByTypeName($typeName, $this->bindingDescriptors, $this->typeDescriptors)
            )
        );
    }

    private function defineType(BindingTypeDescriptor $typeDescriptor)
    {
        return new DefineType($typeDescriptor, $this->discovery);
    }

    private function syncTypeName($typeName)
    {
        return new SyncTypeName($typeName, $this->discovery, $this->typeDescriptors);
    }

    private function addBindingDescriptorToPackageFile(BindingDescriptor $bindingDescriptor)
    {
        return new AddBindingDescriptorToPackageFile($bindingDescriptor, $this->rootPackageFile);
    }

    private function removeBindingDescriptorFromPackageFile(Uuid $uuid)
    {
        return new RemoveBindingDescriptorFromPackageFile($uuid, $this->rootPackageFile);
    }

    private function loadBindingDescriptor(BindingDescriptor $bindingDescriptor, Package $package)
    {
        return new LoadBindingDescriptor($bindingDescriptor, $package, $this->bindingDescriptors, $this->typeDescriptors);
    }

    private function unloadBindingDescriptor(BindingDescriptor $bindingDescriptor)
    {
        return new UnloadBindingDescriptor($bindingDescriptor, $this->bindingDescriptors);
    }

    private function enableBindingUuid(Uuid $uuid, InstallInfo $installInfo)
    {
        return new InterceptedOperation(
            new EnableBindingUuid($uuid, $installInfo),
            new ReloadBindingDescriptorsByUuid($uuid, $this->bindingDescriptors, $this->typeDescriptors)
        );
    }

    private function disableBindingUuid(Uuid $uuid, InstallInfo $installInfo)
    {
        return new InterceptedOperation(
            new DisableBindingUuid($uuid, $installInfo),
            new ReloadBindingDescriptorsByUuid($uuid, $this->bindingDescriptors, $this->typeDescriptors)
        );
    }

    private function bind(BindingDescriptor $bindingDescriptor)
    {
        return new Bind($bindingDescriptor, $this->discovery);
    }

    private function syncBindingUuid(Uuid $uuid)
    {
        return new SyncBindingUuid($uuid, $this->discovery, $this->bindingDescriptors);
    }
}
