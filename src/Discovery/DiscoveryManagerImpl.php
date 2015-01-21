<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Discovery;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Puli\Discovery\Api\Binding\MissingParameterException;
use Puli\Discovery\Api\Binding\NoSuchParameterException;
use Puli\Discovery\Api\DuplicateTypeException;
use Puli\Discovery\Api\EditableDiscovery;
use Puli\Discovery\Api\NoSuchTypeException;
use Puli\Discovery\Api\Validation\ConstraintViolation;
use Puli\RepositoryManager\Api\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Api\Discovery\BindingState;
use Puli\RepositoryManager\Api\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Api\Discovery\CannotDisableBindingException;
use Puli\RepositoryManager\Api\Discovery\CannotEnableBindingException;
use Puli\RepositoryManager\Api\Discovery\DiscoveryManager;
use Puli\RepositoryManager\Api\Discovery\DiscoveryNotEmptyException;
use Puli\RepositoryManager\Api\Discovery\NoSuchBindingException;
use Puli\RepositoryManager\Api\Discovery\TypeNotEnabledException;
use Puli\RepositoryManager\Api\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Api\Package\PackageCollection;
use Puli\RepositoryManager\Api\Package\RootPackage;
use Puli\RepositoryManager\Api\Package\RootPackageFile;
use Puli\RepositoryManager\Package\PackageFileStorage;
use Rhumsaa\Uuid\Uuid;

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
     * @var BindingTypeDescriptorStore
     */
    private $typeStore;

    /**
     * @var BindingDescriptorStore
     */
    private $bindingStore;

    /**
     * Creates a tag manager.
     *
     * @param ProjectEnvironment $environment
     * @param PackageCollection  $packages
     * @param PackageFileStorage $packageFileStorage
     * @param LoggerInterface    $logger
     */
    public function __construct(
        ProjectEnvironment $environment,
        PackageCollection $packages,
        PackageFileStorage $packageFileStorage,
        LoggerInterface $logger = null
    )
    {
        $this->environment = $environment;
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
    public function addBindingType(BindingTypeDescriptor $typeDescriptor)
    {
        $this->assertDiscoveryLoaded();
        $this->assertPackagesLoaded();
        $this->emitWarningForDuplicateTypes();

        if ($this->typeStore->existsAny($typeDescriptor->getName())) {
            throw DuplicateTypeException::forTypeName($typeDescriptor->getName());
        }

        try {
            // First check that the type can be added without errors
            $this->loadAndSyncBindingType($typeDescriptor);

            // Then save the configuration
            $this->rootPackageFile->addTypeDescriptor($typeDescriptor);
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            // Clean up
            $this->unloadAndSyncBindingType($typeDescriptor->getName());
            $this->rootPackageFile->removeTypeDescriptor($typeDescriptor->getName());

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeBindingType($typeName)
    {
        $this->assertDiscoveryLoaded();
        $this->assertPackagesLoaded();

        if (!$this->rootPackageFile->hasTypeDescriptor($typeName)) {
            return;
        }

        // First remove from the configuration. If all else fails, the
        // discovery can be rebuilt completely with the deletion applied.
        $this->rootPackageFile->removeTypeDescriptor($typeName);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        $this->unloadAndSyncBindingType($typeName);

        // Don't include the warning for the removed type unless necessary
        $this->emitWarningForDuplicateTypes();
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingTypes($packageName = null, $state = null)
    {
        $this->assertPackagesLoaded();

        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $types = array();

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();

            foreach ($packageFile->getTypeDescriptors() as $type) {
                if (null === $state || $state === $type->getState()) {
                    $types[] = $type;
                }
            }
        }

        return array_values($types);
    }

    /**
     * {@inheritdoc}
     */
    public function addBinding(BindingDescriptor $bindingDescriptor)
    {
        $this->assertDiscoveryLoaded();
        $this->assertPackagesLoaded();

        $typeName = $bindingDescriptor->getTypeName();

        if (!$this->typeStore->existsAny($typeName)) {
            throw NoSuchTypeException::forTypeName($typeName);
        }

        if (!$this->typeStore->existsEnabled($typeName)) {
            throw TypeNotEnabledException::forTypeName($typeName);
        }

        if ($this->rootPackageFile->hasBindingDescriptor($bindingDescriptor->getUuid())) {
            return;
        }

        try {
            // First check that the binding can actually be loaded and bound
            $this->loadAndSyncBinding($bindingDescriptor, $this->rootPackage);

            // If no error was detected, persist changes to the configuration
            $this->rootPackageFile->addBindingDescriptor($bindingDescriptor);
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            // Clean up
            $this->unloadAndSyncBinding($bindingDescriptor);
            $this->rootPackageFile->removeBindingDescriptor($bindingDescriptor->getUuid());

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeBinding(Uuid $uuid)
    {
        $this->assertDiscoveryLoaded();
        $this->assertPackagesLoaded();

        if (!$this->rootPackageFile->hasBindingDescriptor($uuid)) {
            return;
        }

        // First remove from the configuration. If all else fails, the
        // discovery can be rebuilt completely with the deletion applied.
        $this->rootPackageFile->removeBindingDescriptor($uuid);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        if (!$binding = $this->bindingStore->get($uuid, $this->rootPackage)) {
            return;
        }

        // The binding is not enabled if the binding type was not loaded
        if (!$binding->isEnabled()) {
            return;
        }

        $this->unloadAndSyncBinding($binding);
    }

    /**
     * {@inheritdoc}
     */
    public function enableBinding(Uuid $uuid, $packageName = null)
    {
        $this->assertDiscoveryLoaded();
        $this->assertPackagesLoaded();

        if (!$bindingsToEnable = $this->getBindingsToEnable($uuid, $packageName)) {
            return;
        }

        $wasDisabled = array();

        try {
            foreach ($bindingsToEnable as $packageName => $binding) {
                $installInfo = $this->packages[$packageName]->getInstallInfo();
                $wasDisabled[$uuid->toString()] = $installInfo->hasDisabledBindingUuid($uuid);
                $installInfo->addEnabledBindingUuid($uuid);
                $this->reloadAndSyncBinding($binding);
            }

            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            foreach ($bindingsToEnable as $packageName => $binding) {
                $installInfo = $this->packages[$packageName]->getInstallInfo();
                if ($wasDisabled[$uuid->toString()]) {
                    $installInfo->addDisabledBindingUuid($uuid);
                } else {
                    $installInfo->removeEnabledBindingUuid($uuid);
                }
                $this->reloadAndSyncBinding($binding);
            }

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disableBinding(Uuid $uuid, $packageName = null)
    {
        $this->assertDiscoveryLoaded();
        $this->assertPackagesLoaded();

        if (!$bindingsToDisable = $this->getBindingsToDisable($uuid, $packageName)) {
            return;
        }

        $wasEnabled = array();

        try {
            foreach ($bindingsToDisable as $packageName => $binding) {
                $installInfo = $this->packages[$packageName]->getInstallInfo();
                $wasEnabled[$uuid->toString()] = $installInfo->hasEnabledBindingUuid($uuid);
                $installInfo->addDisabledBindingUuid($uuid);
                $this->reloadAndSyncBinding($binding);
            }

            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            foreach ($bindingsToDisable as $packageName => $binding) {
                $installInfo = $this->packages[$packageName]->getInstallInfo();
                if ($wasEnabled[$uuid->toString()]) {
                    $installInfo->addEnabledBindingUuid($uuid);
                } else {
                    $installInfo->removeDisabledBindingUuid($uuid);
                }
                $this->reloadAndSyncBinding($binding);
            }

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getBindings($packageName = null, $state = null)
    {
        $this->assertPackagesLoaded();

        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $bindings = array();

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();

            foreach ($packageFile->getBindingDescriptors() as $binding) {
                if (null === $state || $state === $binding->getState()) {
                    $bindings[$binding->getUuid()->toString()] = $binding;
                }
            }
        }

        return array_values($bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function findBindings($uuid, $packageName = null)
    {
        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $bindings = array();
        $uuid = $uuid instanceof Uuid ? $uuid->toString() : $uuid;

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();

            foreach ($packageFile->getBindingDescriptors() as $binding) {
                $uuidString = $binding->getUuid()->toString();

                if (0 === strpos($uuidString, $uuid)) {
                    $bindings[$uuidString] = $binding;
                }
            }
        }

        return array_values($bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function buildDiscovery()
    {
        $this->assertDiscoveryLoaded();
        $this->assertPackagesLoaded();
        $this->emitWarningForDuplicateTypes();
        $this->emitWarningForInvalidBindings();

        if (count($this->discovery->getBindings()) > 0 || count($this->discovery->getDefinedTypes()) > 0) {
            throw new DiscoveryNotEmptyException('The discovery is not empty.');
        }

        foreach ($this->typeStore->getTypeNames() as $typeName) {
            $this->syncBindingType($typeName, false);
        }

        foreach ($this->bindingStore->getUuids() as $uuid) {
            $this->syncBinding($this->bindingStore->get($uuid), false);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearDiscovery()
    {
        $this->assertDiscoveryLoaded();

        $this->discovery->clear();
    }

    private function assertDiscoveryLoaded()
    {
        if (!$this->discovery) {
            $this->loadDiscovery();
        }
    }

    private function assertPackagesLoaded()
    {
        if (!$this->typeStore) {
            $this->loadPackages();
        }
    }

    private function assertBindingValid(BindingDescriptor $binding)
    {
        foreach ($binding->getViolations() as $violation) {
            switch ($violation->getCode()) {
                case ConstraintViolation::NO_SUCH_PARAMETER:
                    throw NoSuchParameterException::forParameterName($violation->getParameterName(), $violation->getTypeName());
                case ConstraintViolation::MISSING_PARAMETER:
                    throw MissingParameterException::forParameterName($violation->getParameterName(), $violation->getTypeName());
            }
        }
    }

    private function loadDiscovery()
    {
        $this->discovery = $this->environment->getDiscovery();
    }

    private function loadPackages()
    {
        $this->typeStore = new BindingTypeDescriptorStore();
        $this->bindingStore = new BindingDescriptorStore();

        // First load all the types
        foreach ($this->packages as $package) {
            $this->loadBindingTypesFromPackage($package);
        }

        // Then the bindings for the loaded types
        foreach ($this->packages as $package) {
            $this->loadBindingsFromPackage($package);
        }
    }

    private function emitWarningForDuplicateTypes()
    {
        foreach ($this->typeStore->getTypeNames() as $typeName) {
            if ($this->typeStore->get($typeName)->isDuplicate()) {
                $packageNames = $this->typeStore->getDefiningPackageNames($typeName);
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
        foreach ($this->bindingStore->getUuids() as $uuid) {
            foreach ($this->bindingStore->getAll($uuid) as $packageName => $binding) {
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

    private function loadBindingTypesFromPackage(Package $package)
    {
        foreach ($package->getPackageFile()->getTypeDescriptors() as $bindingType) {
            $this->loadBindingType($bindingType, $package);
        }
    }

    private function loadBindingType(BindingTypeDescriptor $bindingType, Package $package)
    {
        $this->typeStore->add($bindingType, $package);

        $bindingType->load($package);

        $this->updateDuplicateMarksForTypeName($bindingType->getName());
    }

    private function unloadBindingType($typeName, Package $package)
    {
        if ($this->typeStore->exists($typeName, $package)) {
            $this->typeStore->get($typeName, $package)->unload();
            $this->typeStore->remove($typeName, $package);

            if ($this->typeStore->existsAny($typeName)) {
                $this->updateDuplicateMarksForTypeName($typeName);
            }
        }
    }

    private function updateDuplicateMarksForTypeName($typeName)
    {
        $types = $this->typeStore->getAll($typeName);
        $duplicate = count($types) > 1;

        foreach ($types as $type) {
            $type->markDuplicate($duplicate);
        }
    }

    private function loadAndSyncBindingType(BindingTypeDescriptor $bindingType)
    {
        $typeName = $bindingType->getName();
        $wasEnabled = $this->typeStore->existsEnabled($typeName);

        $this->loadBindingType($bindingType, $this->rootPackage);
        $this->syncBindingType($bindingType->getName(), $wasEnabled);
    }

    private function unloadAndSyncBindingType($typeName)
    {
        $wasEnabled = $this->typeStore->existsEnabled($typeName);

        $this->unloadBindingType($typeName, $this->rootPackage);
        $this->syncBindingType($typeName, $wasEnabled);
    }

    private function syncBindingType($typeName, $wasEnabled)
    {
        $isEnabled = $this->typeStore->existsEnabled($typeName);

        if ($wasEnabled && !$isEnabled) {
            $this->discovery->undefineType($typeName);
            $this->reloadAndSyncBindingsForType($typeName);
        } elseif (!$wasEnabled && $isEnabled) {
            $bindingType = $this->typeStore->get($typeName);
            $this->discovery->defineType($bindingType->toBindingType());
            $this->reloadAndSyncBindingsForType($typeName);
        }
    }

    private function loadBindingsFromPackage(Package $package)
    {
        foreach ($package->getPackageFile()->getBindingDescriptors() as $bindingDescriptor) {
            $this->loadBinding($bindingDescriptor, $package);
        }
    }

    private function loadBinding(BindingDescriptor $binding, Package $package)
    {
        $this->bindingStore->add($binding, $package);

        $type = $this->typeStore->existsAny($binding->getTypeName())
            ? $this->typeStore->get($binding->getTypeName())
            : null;

        $binding->load($package, $type);

        $this->updateDuplicateMarksForUuid($binding->getUuid());
    }

    private function unloadBinding(BindingDescriptor $binding)
    {
        $this->bindingStore->remove($binding->getUuid(), $binding->getContainingPackage());
        $binding->unload();

        $this->updateDuplicateMarksForUuid($binding->getUuid());
    }

    private function reloadBinding(BindingDescriptor $binding)
    {
        $package = $binding->getContainingPackage();
        $type = $this->typeStore->existsAny($binding->getTypeName())
            ? $this->typeStore->get($binding->getTypeName())
            : null;

        $binding->unload();
        $binding->load($package, $type);

        $this->updateDuplicateMarksForUuid($binding->getUuid());
    }

    private function updateDuplicateMarksForUuid(Uuid $uuid)
    {
        if (!$this->bindingStore->existsAny($uuid)) {
            return;
        }

        $rootPackageName = $this->rootPackage->getName();
        $bindings = $this->bindingStore->getAll($uuid);

        if (1 === count($bindings)) {
            reset($bindings)->markDuplicate(false);

            return;
        }

        $oneEnabled = false;

        // Mark all bindings but one as duplicates
        // Don't mark root bindings as duplicates if possible
        if (isset($bindings[$rootPackageName])) {
            // Move root binding to front
            array_unshift($bindings, $bindings[$rootPackageName]);
            unset($bindings[$rootPackageName]);
        }

        foreach ($bindings as $binding) {
            if (!$oneEnabled && ($binding->isEnabled() || $binding->isDuplicate())) {
                $binding->markDuplicate(false);
                $oneEnabled = true;
            } else {
                $binding->markDuplicate(true);
            }
        }
    }

    private function loadAndSyncBinding(BindingDescriptor $binding, Package $package)
    {
        $wasEnabled = $this->bindingStore->existsEnabled($binding->getUuid());

        $this->loadBinding($binding, $package);

        if (!$binding->isHeldBack() && !$binding->isIgnored()) {
            $this->assertBindingValid($binding);
        }

        $this->syncBinding($binding, $wasEnabled);
    }

    private function unloadAndSyncBinding(BindingDescriptor $binding)
    {
        $wasEnabled = $this->bindingStore->existsEnabled($binding->getUuid());

        // Keep a loaded clone that has access to the type's default parameter
        // values. The default values are required for the unbind() call.
        $clone = clone $binding;

        $this->unloadBinding($binding);
        $this->syncBinding($clone, $wasEnabled);
    }

    private function reloadAndSyncBinding(BindingDescriptor $binding)
    {
        $wasEnabled = $this->bindingStore->existsEnabled($binding->getUuid());

        $this->reloadBinding($binding);
        $this->syncBinding($binding, $wasEnabled);
    }

    private function reloadAndSyncBindingsForType($typeName, $state = null)
    {
        $states = $state ? (array) $state : BindingState::all();

        foreach ($this->packages as $packageName => $package) {
            $packageFile = $package->getPackageFile();

            foreach ($packageFile->getBindingDescriptors() as $binding) {
                if (!in_array($binding->getState(), $states)) {
                    continue;
                }

                if ($typeName !== $binding->getTypeName()) {
                    continue;
                }

                $this->reloadAndSyncBinding($binding);
            }
        }
    }

    private function syncBinding(BindingDescriptor $binding, $wasEnabled)
    {
        $isEnabled = $this->bindingStore->existsEnabled($binding->getUuid());

        if (!$wasEnabled && $isEnabled) {
            $this->discovery->bind(
                $binding->getQuery(),
                $binding->getTypeName(),
                $binding->getParameterValues(),
                $binding->getLanguage()
            );
        } elseif ($wasEnabled && !$isEnabled) {
            $this->discovery->unbind(
                $binding->getQuery(),
                $binding->getTypeName(),
                $binding->getParameterValues(),
                $binding->getLanguage()
            );
        }
    }

    /**
     * @param Uuid $uuid
     * @param      $packageName
     *
     * @return BindingDescriptor[]
     */
    private function getBindingsToEnable(Uuid $uuid, $packageName = null)
    {
        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $bindingsByPackage = $this->getBindingsByPackage($uuid, $packageNames);

        if (!$bindingsByPackage) {
            throw NoSuchBindingException::forUuid($uuid);
        }

        $bindingsToEnable = array();

        foreach ($bindingsByPackage as $packageName => $binding) {
            $package = $this->packages[$packageName];

            if ($package instanceof RootPackage) {
                throw CannotEnableBindingException::rootPackageNotAccepted($uuid, $package->getName());
            }

            $installInfo = $package->getInstallInfo();

            if (!$installInfo || $installInfo->hasEnabledBindingUuid($uuid)) {
                continue;
            }

            if ($binding->isHeldBack()) {
                throw CannotEnableBindingException::typeNotLoaded($uuid, $packageName);
            }

            if ($binding->isIgnored()) {
                throw CannotEnableBindingException::duplicateType($uuid, $packageName);
            }

            $bindingsToEnable[$packageName] = $binding;
        }

        return $bindingsToEnable;
    }

    /**
     * @param Uuid $uuid
     * @param      $packageName
     *
     * @return BindingDescriptor[]
     */
    private function getBindingsToDisable(Uuid $uuid, $packageName = null)
    {
        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $bindingsByPackage = $this->getBindingsByPackage($uuid, $packageNames);

        if (!$bindingsByPackage) {
            throw NoSuchBindingException::forUuid($uuid);
        }

        $bindingsToDisable = array();

        foreach ($bindingsByPackage as $packageName => $binding) {
            $package = $this->packages[$packageName];

            if ($package instanceof RootPackage) {
                throw CannotDisableBindingException::rootPackageNotAccepted($uuid, $package->getName());
            }

            $installInfo = $package->getInstallInfo();

            if (!$installInfo || $installInfo->hasDisabledBindingUuid($uuid)) {
                continue;
            }

            if ($binding->isHeldBack()) {
                throw CannotDisableBindingException::typeNotLoaded($uuid, $packageName);
            }

            if ($binding->isIgnored()) {
                throw CannotDisableBindingException::duplicateType($uuid, $packageName);
            }

            $bindingsToDisable[$packageName] = $binding;
        }

        return $bindingsToDisable;
    }

    /**
     * @param Uuid  $uuid
     * @param array $packageNames
     *
     * @return BindingDescriptor[]
     */
    private function getBindingsByPackage(Uuid $uuid, array $packageNames)
    {
        $bindingsByPackage = array();

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();

            if ($packageFile->hasBindingDescriptor($uuid)) {
                $bindingsByPackage[$packageName] = $packageFile->getBindingDescriptor($uuid);
            }
        }

        return $bindingsByPackage;
    }
}
