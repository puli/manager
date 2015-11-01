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
use Puli\Discovery\Api\EditableDiscovery;
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Discovery\DiscoveryNotEmptyException;
use Puli\Manager\Api\Discovery\DuplicateBindingException;
use Puli\Manager\Api\Discovery\DuplicateTypeException;
use Puli\Manager\Api\Discovery\NoSuchBindingException;
use Puli\Manager\Api\Discovery\NoSuchTypeException;
use Puli\Manager\Api\Discovery\TypeNotEnabledException;
use Puli\Manager\Api\NonRootPackageExpectedException;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Assert\Assert;
use Puli\Manager\Discovery\Binding\AddBinding;
use Puli\Manager\Discovery\Binding\AddBindingDescriptorToPackageFile;
use Puli\Manager\Discovery\Binding\BindingDescriptorCollection;
use Puli\Manager\Discovery\Binding\DisableBindingUuid;
use Puli\Manager\Discovery\Binding\EnableBindingUuid;
use Puli\Manager\Discovery\Binding\LoadBindingDescriptor;
use Puli\Manager\Discovery\Binding\ReloadBindingDescriptorsByTypeName;
use Puli\Manager\Discovery\Binding\ReloadBindingDescriptorsByUuid;
use Puli\Manager\Discovery\Binding\RemoveBindingDescriptorFromPackageFile;
use Puli\Manager\Discovery\Binding\SyncBindingUuid;
use Puli\Manager\Discovery\Binding\UnloadBindingDescriptor;
use Puli\Manager\Discovery\Type\AddBindingType;
use Puli\Manager\Discovery\Type\AddTypeDescriptorToPackageFile;
use Puli\Manager\Discovery\Type\BindingTypeDescriptorCollection;
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
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DiscoveryManagerImpl implements DiscoveryManager
{
    /**
     * @var ProjectContext
     */
    private $context;

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
     * @param ProjectContext       $context
     * @param EditableDiscovery    $discovery
     * @param PackageCollection    $packages
     * @param PackageFileStorage   $packageFileStorage
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        ProjectContext $context,
        EditableDiscovery $discovery,
        PackageCollection $packages,
        PackageFileStorage $packageFileStorage,
        LoggerInterface $logger = null
    ) {
        $this->context = $context;
        $this->discovery = $discovery;
        $this->packages = $packages;
        $this->packageFileStorage = $packageFileStorage;
        $this->rootPackage = $packages->getRootPackage();
        $this->rootPackageFile = $context->getRootPackageFile();
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function addRootTypeDescriptor(BindingTypeDescriptor $typeDescriptor, $flags = 0)
    {
        Assert::integer($flags, 'The argument $flags must be a boolean.');

        $this->assertPackagesLoaded();
        $this->emitWarningForDuplicateTypes();

        $typeName = $typeDescriptor->getTypeName();

        if (!($flags & self::OVERRIDE) && $this->typeDescriptors->contains($typeName)) {
            throw DuplicateTypeException::forTypeName($typeName);
        }

        $tx = new Transaction();

        try {
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
    public function removeRootTypeDescriptor($typeName)
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
    public function removeRootTypeDescriptors(Expression $expr)
    {
        $this->assertPackagesLoaded();

        $tx = new Transaction();
        $syncBindingOps = array();

        try {
            foreach ($this->getRootTypeDescriptors() as $typeDescriptor) {
                if ($expr->evaluate($typeDescriptor)) {
                    $typeName = $typeDescriptor->getTypeName();

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
    public function clearRootTypeDescriptors()
    {
        $this->removeRootTypeDescriptors(Expr::true());
    }

    /**
     * {@inheritdoc}
     */
    public function getRootTypeDescriptor($typeName)
    {
        return $this->getTypeDescriptor($typeName, $this->rootPackage->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function getRootTypeDescriptors()
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
    public function findRootTypeDescriptors(Expression $expr)
    {
        $expr = Expr::method('getContainingPackage', Expr::same($this->rootPackage))
            ->andX($expr);

        return $this->findTypeDescriptors($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootTypeDescriptor($typeName)
    {
        return $this->hasTypeDescriptor($typeName, $this->rootPackage->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootTypeDescriptors(Expression $expr = null)
    {
        $expr2 = Expr::method('getContainingPackage', Expr::same($this->rootPackage));

        if ($expr) {
            $expr2 = $expr2->andX($expr);
        }

        return $this->hasTypeDescriptors($expr2);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeDescriptor($typeName, $packageName)
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
    public function getTypeDescriptors()
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
    public function findTypeDescriptors(Expression $expr)
    {
        $this->assertPackagesLoaded();

        $typeDescriptors = array();

        foreach ($this->typeDescriptors->toArray() as $typeName => $descriptorsByPackage) {
            foreach ($descriptorsByPackage as $typeDescriptor) {
                if ($expr->evaluate($typeDescriptor)) {
                    $typeDescriptors[] = $typeDescriptor;
                }
            }
        }

        return $typeDescriptors;
    }

    /**
     * {@inheritdoc}
     */
    public function hasTypeDescriptor($typeName, $packageName = null)
    {
        Assert::nullOrString($packageName, 'The package name must be a string or null. Got: %s');

        $this->assertPackagesLoaded();

        return $this->typeDescriptors->contains($typeName, $packageName);
    }

    /**
     * {@inheritdoc}
     */
    public function hasTypeDescriptors(Expression $expr = null)
    {
        $this->assertPackagesLoaded();

        if (!$expr) {
            return !$this->typeDescriptors->isEmpty();
        }

        foreach ($this->typeDescriptors->toArray() as $typeName => $descriptorsByPackage) {
            foreach ($descriptorsByPackage as $typeDescriptor) {
                if ($expr->evaluate($typeDescriptor)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function addRootBindingDescriptor(BindingDescriptor $bindingDescriptor, $flags = 0)
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
        $exists = $this->bindingDescriptors->contains($uuid);
        $existsInNonRoot = $exists
            ? !($this->bindingDescriptors->get($uuid)->getContainingPackage() instanceof RootPackage)
            : false;

        // We can only override bindings in the root package
        if ($existsInNonRoot || ($exists && !($flags & self::OVERRIDE))) {
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
    public function removeRootBindingDescriptor(Uuid $uuid)
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
    public function removeRootBindingDescriptors(Expression $expr)
    {
        $this->assertPackagesLoaded();

        $tx = new Transaction();

        try {
            foreach ($this->getRootBindingDescriptors() as $bindingDescriptor) {
                if ($expr->evaluate($bindingDescriptor)) {
                    $syncOp = $this->syncBindingUuid($bindingDescriptor->getUuid());
                    $syncOp->takeSnapshot();

                    $tx->execute($this->unloadBindingDescriptor($bindingDescriptor));
                    $tx->execute($syncOp);
                    $tx->execute($this->removeBindingDescriptorFromPackageFile($bindingDescriptor->getUuid()));
                }
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
    public function clearRootBindingDescriptors()
    {
        $this->removeRootBindingDescriptors(Expr::true());
    }

    /**
     * {@inheritdoc}
     */
    public function getRootBindingDescriptor(Uuid $uuid)
    {
        $binding = $this->getBindingDescriptor($uuid);

        if (!$binding->getContainingPackage() instanceof RootPackage) {
            throw NoSuchBindingException::forUuidAndPackage($uuid, $this->rootPackage->getName());
        }

        return $binding;
    }

    /**
     * {@inheritdoc}
     */
    public function getRootBindingDescriptors()
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
    public function findRootBindingDescriptors(Expression $expr)
    {
        $expr = Expr::method('getContainingPackage', Expr::same($this->rootPackage))
            ->andX($expr);

        return $this->findBindingDescriptors($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootBindingDescriptor(Uuid $uuid)
    {
        return $this->hasBindingDescriptor($uuid) && $this->getBindingDescriptor($uuid)->getContainingPackage() instanceof RootPackage;
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootBindingDescriptors(Expression $expr = null)
    {
        $expr2 = Expr::method('getContainingPackage', Expr::same($this->rootPackage));

        if ($expr) {
            $expr2 = $expr2->andX($expr);
        }

        return $this->hasBindingDescriptors($expr2);
    }

    /**
     * {@inheritdoc}
     */
    public function enableBindingDescriptor(Uuid $uuid)
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
    public function disableBindingDescriptor(Uuid $uuid)
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
    public function removeObsoleteDisabledBindingDescriptors()
    {
        $this->assertPackagesLoaded();

        $removedUuidsByPackage = array();

        try {
            foreach ($this->rootPackageFile->getInstallInfos() as $installInfo) {
                foreach ($installInfo->getDisabledBindingUuids() as $uuid) {
                    if (!$this->bindingDescriptors->contains($uuid)) {
                        $installInfo->removeDisabledBindingUuid($uuid);
                        $removedUuidsByPackage[$installInfo->getPackageName()][] = $uuid;
                    }
                }
            }

            $this->saveRootPackageFile();
        } catch (Exception $e) {
            foreach ($removedUuidsByPackage as $packageName => $removedUuids) {
                $installInfo = $this->rootPackageFile->getInstallInfo($packageName);

                foreach ($removedUuids as $uuid) {
                    $installInfo->addDisabledBindingUuid($uuid);
                }
            }

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingDescriptor(Uuid $uuid)
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
    public function getBindingDescriptors()
    {
        $this->assertPackagesLoaded();

        return array_values($this->bindingDescriptors->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function findBindingDescriptors(Expression $expr)
    {
        $this->assertPackagesLoaded();

        $descriptors = array();

        foreach ($this->bindingDescriptors->toArray() as $descriptor) {
            if ($expr->evaluate($descriptor)) {
                $descriptors[] = $descriptor;
            }
        }

        return $descriptors;
    }

    /**
     * {@inheritdoc}
     */
    public function hasBindingDescriptor(Uuid $uuid)
    {
        $this->assertPackagesLoaded();

        return $this->bindingDescriptors->contains($uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function hasBindingDescriptors(Expression $expr = null)
    {
        $this->assertPackagesLoaded();

        if (!$expr) {
            return !$this->bindingDescriptors->isEmpty();
        }

        foreach ($this->bindingDescriptors->toArray() as $bindingDescriptor) {
            if ($expr->evaluate($bindingDescriptor)) {
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

        if ($this->discovery->hasBindings() || $this->discovery->hasBindingTypes()) {
            throw new DiscoveryNotEmptyException('The discovery is not empty.');
        }

        $tx = new Transaction();

        try {
            foreach ($this->typeDescriptors->toArray() as $typeName => $descriptorsByPackage) {
                foreach ($descriptorsByPackage as $typeDescriptor) {
                    if ($typeDescriptor->isEnabled()) {
                        $tx->execute($this->addBindingType($typeDescriptor));
                    }
                }
            }

            foreach ($this->bindingDescriptors->toArray() as $bindingDescriptor) {
                if ($bindingDescriptor->isEnabled()) {
                    $tx->execute($this->addBinding($bindingDescriptor));
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
        $this->discovery->removeBindingTypes();
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

        foreach ($bindingDescriptor->getLoadErrors() as $exception) {
            throw $exception;
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
            foreach ($binding->getLoadErrors() as $exception) {
                $this->logger->warning(sprintf(
                    'The binding "%s" in package "%s" is invalid: %s',
                    $binding->getUuid()->toString(),
                    $binding->getContainingPackage()->getName(),
                    $exception->getMessage()
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
        $typeName = $typeDescriptor->getTypeName();

        return new InterceptedOperation(
            new LoadTypeDescriptor($typeDescriptor, $package, $this->typeDescriptors),
            array(
                new UpdateDuplicateMarksForTypeName($typeName, $this->typeDescriptors),
                new ReloadBindingDescriptorsByTypeName($typeName, $this->bindingDescriptors, $this->typeDescriptors),
            )
        );
    }

    private function unloadTypeDescriptor(BindingTypeDescriptor $typeDescriptor)
    {
        $typeName = $typeDescriptor->getTypeName();

        return new InterceptedOperation(
            new UnloadTypeDescriptor($typeDescriptor, $this->typeDescriptors),
            array(
                new UpdateDuplicateMarksForTypeName($typeName, $this->typeDescriptors),
                new ReloadBindingDescriptorsByTypeName($typeName, $this->bindingDescriptors, $this->typeDescriptors),
            )
        );
    }

    private function addBindingType(BindingTypeDescriptor $typeDescriptor)
    {
        return new AddBindingType($typeDescriptor, $this->discovery);
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

    private function addBinding(BindingDescriptor $bindingDescriptor)
    {
        return new AddBinding($bindingDescriptor, $this->discovery);
    }

    private function syncBindingUuid(Uuid $uuid)
    {
        return new SyncBindingUuid($uuid, $this->discovery, $this->bindingDescriptors);
    }
}
