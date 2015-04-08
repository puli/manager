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
use Puli\Discovery\Api\DuplicateTypeException;
use Puli\Discovery\Api\EditableDiscovery;
use Puli\Discovery\Api\NoSuchTypeException;
use Puli\Discovery\Api\Validation\ConstraintViolation;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\CannotDisableBindingException;
use Puli\Manager\Api\Discovery\CannotEnableBindingException;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Discovery\DiscoveryNotEmptyException;
use Puli\Manager\Api\Discovery\NoSuchBindingException;
use Puli\Manager\Api\Discovery\TypeNotEnabledException;
use Puli\Manager\Api\Environment\ProjectEnvironment;
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
use Puli\Manager\Discovery\Binding\UpdateOverriddenMarksForUuid;
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
    public function addBindingType(BindingTypeDescriptor $typeDescriptor, $flags = 0)
    {
        $this->assertPackagesLoaded();
        $this->emitWarningForDuplicateTypes();

        if (!($flags & self::NO_DUPLICATE_CHECK) && $this->typeDescriptors->contains($typeDescriptor->getName())) {
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
    public function removeBindingType($typeName)
    {
        // Only check that this is a string. The error message "not found" is
        // more helpful than e.g. "type name must contain /".
        Assert::string($typeName, 'The type name must be a string');

        $this->assertPackagesLoaded();

        if (!$this->rootPackageFile->hasTypeDescriptor($typeName)) {
            return;
        }

        $tx = new Transaction();

        try {
            $tx->execute($this->removeTypeDescriptorFromPackageFile($typeName));

            if ($this->typeDescriptors->contains($typeName, $this->rootPackage->getName())) {
                $typeDescriptor = $this->typeDescriptors->get($typeName, $this->rootPackage->getName());
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
    public function getBindingType($typeName, $packageName = null)
    {
        Assert::nullOrString($packageName, 'The package name must be a string or null. Got: %s');

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
    public function addBinding(BindingDescriptor $bindingDescriptor, $flags = 0)
    {
        $this->assertPackagesLoaded();

        if (!($flags & self::NO_TYPE_CHECK)) {
            $typeName = $bindingDescriptor->getTypeName();

            if (!$this->typeDescriptors->contains($typeName)) {
                throw NoSuchTypeException::forTypeName($typeName);
            }

            if (!$this->typeDescriptors->getEnabled($typeName)) {
                throw TypeNotEnabledException::forTypeName($typeName);
            }
        }

        if ($this->rootPackageFile->hasBindingDescriptor($bindingDescriptor->getUuid())) {
            return;
        }

        $tx = new Transaction();

        try {
            $syncOp = $this->syncBindingUuid($bindingDescriptor->getUuid());
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
    public function removeBinding(Uuid $uuid)
    {
        $this->assertPackagesLoaded();

        if (!$this->rootPackageFile->hasBindingDescriptor($uuid)) {
            return;
        }

        $tx = new Transaction();

        try {
            if ($this->bindingDescriptors->contains($uuid, $this->rootPackage->getName())) {
                $bindingDescriptor = $this->bindingDescriptors->get($uuid, $this->rootPackage->getName());
                $syncOp = $this->syncBindingUuid($uuid);
                $syncOp->takeSnapshot();

                $tx->execute($this->unloadBindingDescriptor($bindingDescriptor));
                $tx->execute($syncOp);
            }

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
    public function enableBinding(Uuid $uuid, $packageName = null)
    {
        $this->assertPackagesLoaded();

        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();

        Assert::allString($packageNames, 'The package names must be strings. Got: %s');

        if (!$bindingDescriptors = $this->getBindingsByUuid($uuid, $packageNames)) {
            throw NoSuchBindingException::forUuid($uuid);
        }

        if (!$installInfos = $this->getInstallInfosForEnable($uuid, $bindingDescriptors)) {
            return;
        }

        $tx = new Transaction();

        try {
            $syncOp = $this->syncBindingUuid($uuid);
            $syncOp->takeSnapshot();

            foreach ($installInfos as $installInfo) {
                $tx->execute($this->enableBindingUuid($uuid, $installInfo));
            }

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
    public function disableBinding(Uuid $uuid, $packageName = null)
    {
        $this->assertPackagesLoaded();

        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();

        Assert::allString($packageNames, 'The package names must be strings. Got: %s');

        if (!$bindingDescriptors = $this->getBindingsByUuid($uuid, $packageNames)) {
            throw NoSuchBindingException::forUuid($uuid);
        }

        if (!$installInfos = $this->getInstallInfosForDisable($uuid, $bindingDescriptors)) {
            return;
        }

        $tx = new Transaction();

        try {
            $syncOp = $this->syncBindingUuid($uuid);
            $syncOp->takeSnapshot();

            foreach ($installInfos as $installInfo) {
                $tx->execute($this->disableBindingUuid($uuid, $installInfo));
            }

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
    public function getBinding(Uuid $uuid, $packageName = null)
    {
        Assert::nullOrString($packageName, 'The package name must be a string or null. Got: %s');

        $this->assertPackagesLoaded();

        if (!$this->bindingDescriptors->contains($uuid, $packageName)) {
            throw NoSuchBindingException::forUuid($uuid);
        }

        return $this->bindingDescriptors->get($uuid, $packageName);
    }

    /**
     * {@inheritdoc}
     */
    public function getBindings()
    {
        $this->assertPackagesLoaded();

        $bindings = array();

        foreach ($this->bindingDescriptors->toArray() as $uuidString => $bindingsByPackage) {
            foreach ($bindingsByPackage as $binding) {
                $bindings[] = $binding;
            }
        }

        return $bindings;
    }

    /**
     * {@inheritdoc}
     */
    public function findBindings(Expression $expr)
    {
        $this->assertPackagesLoaded();

        $bindings = array();

        foreach ($this->bindingDescriptors->toArray() as $uuidString => $bindingsByPackage) {
            foreach ($bindingsByPackage as $binding) {
                if ($binding->match($expr)) {
                    $bindings[] = $binding;
                }
            }
        }

        return $bindings;
    }

    /**
     * {@inheritdoc}
     */
    public function hasBinding(Uuid $uuid, $packageName = null)
    {
        Assert::nullOrString($packageName, 'The package name must be a string or null. Got: %s');

        $this->assertPackagesLoaded();

        return $this->bindingDescriptors->contains($uuid, $packageName);
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

        foreach ($this->bindingDescriptors->toArray() as $uuidString => $bindingsByPackage) {
            foreach ($bindingsByPackage as $binding) {
                if ($binding->match($expr)) {
                    return true;
                }
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
            foreach ($this->typeDescriptors->getTypeNames() as $typeName) {
                if ($typeDescriptor = $this->typeDescriptors->getEnabled($typeName)) {
                    $tx->execute($this->defineType($typeDescriptor));
                }
            }

            foreach ($this->bindingDescriptors->getUuids() as $uuid) {
                if ($bindingDescriptor = $this->bindingDescriptors->getEnabled($uuid)) {
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
        if ($bindingDescriptor->isHeldBack()) {
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
                $this->loadBindingDescriptor($bindingDescriptor, $package)->execute();
            }
        }
    }

    private function emitWarningForDuplicateTypes()
    {
        foreach ($this->typeDescriptors->getTypeNames() as $typeName) {
            if ($this->typeDescriptors->get($typeName)->isDuplicate()) {
                $packageNames = $this->typeDescriptors->getPackageNames($typeName);
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
        foreach ($this->bindingDescriptors->getUuids() as $uuid) {
            foreach ($this->bindingDescriptors->listByUuid($uuid) as $packageName => $binding) {
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
                        $uuid->toString(),
                        $packageName,
                        $reason
                    ));
                }
            }
        }
    }

    /**
     * @param Uuid $uuid
     * @param BindingDescriptor[] $bindingDescriptors
     *
     * @return InstallInfo[]
     */
    private function getInstallInfosForEnable(Uuid $uuid, array $bindingDescriptors)
    {
        $installInfos = array();

        foreach ($bindingDescriptors as $bindingDescriptor) {
            $package = $bindingDescriptor->getContainingPackage();

            if ($package instanceof RootPackage) {
                throw CannotEnableBindingException::rootPackageNotAccepted($uuid, $package->getName());
            }

            $installInfo = $package->getInstallInfo();

            if (!$installInfo || $installInfo->hasEnabledBindingUuid($uuid)) {
                continue;
            }

            if ($bindingDescriptor->isHeldBack()) {
                throw CannotEnableBindingException::typeNotLoaded($uuid, $package->getName());
            }

            $installInfos[] = $installInfo;
        }

        return $installInfos;
    }

    /**
     * @param Uuid $uuid
     * @param BindingDescriptor[] $bindingDescriptors
     *
     * @return InstallInfo[]
     */
    private function getInstallInfosForDisable(Uuid $uuid, array $bindingDescriptors)
    {
        $installInfos = array();

        foreach ($bindingDescriptors as $bindingDescriptor) {
            $package = $bindingDescriptor->getContainingPackage();

            if ($package instanceof RootPackage) {
                throw CannotDisableBindingException::rootPackageNotAccepted($uuid, $package->getName());
            }

            $installInfo = $package->getInstallInfo();

            if (!$installInfo || $installInfo->hasDisabledBindingUuid($uuid)) {
                continue;
            }

            if ($bindingDescriptor->isHeldBack()) {
                throw CannotDisableBindingException::typeNotLoaded($uuid, $package->getName());
            }

            $installInfos[] = $installInfo;
        }

        return $installInfos;
    }

    /**
     * @param Uuid  $uuid
     * @param array $packageNames
     *
     * @return BindingDescriptor[]
     */
    private function getBindingsByUuid(Uuid $uuid, array $packageNames)
    {
        if (!$this->bindingDescriptors->contains($uuid)) {
            return array();
        }

        $bindingDescriptors = array();
        $descriptorsByPackage = $this->bindingDescriptors->listByUuid($uuid);

        foreach ($packageNames as $packageName) {
            if (isset($descriptorsByPackage[$packageName])) {
                $bindingDescriptors[] = $descriptorsByPackage[$packageName];
            }
        }

        return $bindingDescriptors;
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
        return new InterceptedOperation(
            new LoadBindingDescriptor($bindingDescriptor, $package, $this->bindingDescriptors, $this->typeDescriptors),
            new UpdateOverriddenMarksForUuid($bindingDescriptor->getUuid(), $this->bindingDescriptors, $this->rootPackage->getName())
        );
    }

    private function unloadBindingDescriptor(BindingDescriptor $bindingDescriptor)
    {
        return new InterceptedOperation(
            new UnloadBindingDescriptor($bindingDescriptor, $this->bindingDescriptors),
            new UpdateOverriddenMarksForUuid($bindingDescriptor->getUuid(), $this->bindingDescriptors, $this->rootPackage->getName())
        );
    }

    private function enableBindingUuid(Uuid $uuid, InstallInfo $installInfo)
    {
        return new InterceptedOperation(
            new EnableBindingUuid($uuid, $installInfo),
            array(
                new ReloadBindingDescriptorsByUuid($uuid, $this->bindingDescriptors, $this->typeDescriptors),
                new UpdateOverriddenMarksForUuid($uuid, $this->bindingDescriptors, $this->rootPackage->getName())
            )
        );
    }

    private function disableBindingUuid(Uuid $uuid, InstallInfo $installInfo)
    {
        return new InterceptedOperation(
            new DisableBindingUuid($uuid, $installInfo),
            array(
                new ReloadBindingDescriptorsByUuid($uuid, $this->bindingDescriptors, $this->typeDescriptors),
                new UpdateOverriddenMarksForUuid($uuid, $this->bindingDescriptors, $this->rootPackage->getName())
            )
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
