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
use Puli\RepositoryManager\Discovery\Store\BindingStore;
use Puli\RepositoryManager\Discovery\Store\BindingTypeStore;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageCollection;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\RootPackage;
use Rhumsaa\Uuid\Uuid;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DiscoveryManager
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
     * @var BindingTypeStore
     */
    private $typeStore;

    /**
     * @var BindingStore
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
     * Returns the manager's environment.
     *
     * @return ProjectEnvironment The project environment.
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Adds a new binding type.
     *
     * @param BindingTypeDescriptor $bindingType The binding type to add.
     *
     * @throws DuplicateTypeException If the type is already defined.
     */
    public function addBindingType(BindingTypeDescriptor $bindingType)
    {
        $this->assertDiscoveryLoaded();
        $this->assertPackagesLoaded();
        $this->emitWarningForDuplicateTypes();

        if ($this->typeStore->existsAny($bindingType->getName())) {
            throw DuplicateTypeException::forTypeName($bindingType->getName());
        }

        try {
            // First check that the type can be added without errors
            $this->loadBindingTypeAndDefine($bindingType);

            // Then save the configuration
            $this->rootPackageFile->addTypeDescriptor($bindingType);
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            // Clean up
            $this->unloadBindingTypeAndUndefine($bindingType->getName());
            $this->rootPackageFile->removeTypeDescriptor($bindingType->getName());

            throw $e;
        }
    }

    /**
     * Removes a binding type.
     *
     * @param string $typeName The name of the type to remove.
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

        $this->unloadBindingTypeAndUndefine($typeName);

        // Don't include the warning for the removed type unless necessary
        $this->emitWarningForDuplicateTypes();
    }

    /**
     * Returns all binding types.
     *
     * You can optionally filter types by one or multiple package names.
     *
     * @param string|string[] $packageName The package name(s) to filter by.
     * @param int             $state       The state of the types to return.
     *
     * @return BindingTypeDescriptor[] The binding types.
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
     * Adds a new binding.
     *
     * @param        $query
     * @param        $typeName
     * @param array  $parameterValues
     * @param string $language
     */
    public function addBinding($query, $typeName, array $parameterValues = array(), $language = 'glob')
    {
        $this->assertDiscoveryLoaded();
        $this->assertPackagesLoaded();

        if (!$this->typeStore->existsAny($typeName)) {
            throw NoSuchTypeException::forTypeName($typeName);
        }

        if (!$this->typeStore->existsEnabled($typeName)) {
            throw TypeNotEnabledException::forTypeName($typeName);
        }

        $binding = BindingDescriptor::create($query, $typeName, $parameterValues, $language);

        if ($this->rootPackageFile->hasBindingDescriptor($binding->getUuid())) {
            return;
        }

        try {
            // First check that the binding can actually be loaded and bound
            $this->loadBindingAndBind($binding);

            // If no error was detected, persist changes to the configuration
            $this->rootPackageFile->addBindingDescriptor($binding);
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            // Clean up
            $this->unloadBindingAndUnbind($binding);
            $this->rootPackageFile->removeBindingDescriptor($binding->getUuid());

            throw $e;
        }
    }

    /**
     * Removes a binding.
     *
     * @param Uuid $uuid The UUID of the binding.
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

        $this->unloadBindingAndUnbind($binding);
    }

    /**
     * Enables a binding.
     *
     * @param Uuid            $uuid        The UUID of the binding.
     * @param string|string[] $packageName The package name to enable the
     *                                     binding in. Useful if the same
     *                                     binding exists in multiple packages.
     *
     * @throws NoSuchBindingException If the binding could not be found.
     * @throws CannotEnableBindingException If the binding could not be enabled.
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
                $this->reloadBindingAndUpdate($binding, $this->packages[$packageName]);
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
                $this->reloadBindingAndUpdate($binding, $this->packages[$packageName]);
            }

            throw $e;
        }
    }

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
                $this->reloadBindingAndUpdate($binding, $this->packages[$packageName]);
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
                $this->reloadBindingAndUpdate($binding, $this->packages[$packageName]);
            }

            throw $e;
        }
    }

    /**
     * Returns all bindings.
     *
     * You can optionally filter types by one or multiple package names.
     *
     * @param string|string[] $packageName The package name(s) to filter by.
     * @param int             $state       The state of the bindings to return.
     *
     * @return BindingDescriptor[] The enabled bindings.
     */
    public function getBindings($packageName = null, $state = BindingState::ENABLED)
    {
        $this->assertPackagesLoaded();

        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $bindings = array();

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();

            foreach ($packageFile->getBindingDescriptors() as $binding) {
                if ($state === $binding->getState()) {
                    $bindings[$binding->getUuid()->toString()] = $binding;
                }
            }
        }

        return array_values($bindings);
    }

    /**
     * Returns all bindings with the given UUID (prefix).
     *
     * @param Uuid|string     $uuid        The UUID (prefix) to search.
     * @param string|string[] $packageName The package name(s) to filter by.
     *
     * @return BindingDescriptor[] The bindings.
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
     * Builds the resource discovery.
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
            $bindingType = $this->typeStore->get($typeName);

            if ($bindingType->isEnabled()) {
                $this->defineType($bindingType);
            }
        }

        foreach ($this->bindingStore->getUuids() as $uuid) {
            $binding = $this->bindingStore->get($uuid);

            if ($binding->isEnabled()) {
                $this->bind($binding);
            }
        }
    }

    /**
     * Clears all contents of the resource discovery.
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
        $this->typeStore = new BindingTypeStore();
        $this->bindingStore = new BindingStore();

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
            if ($this->typeStore->isDuplicate($typeName)) {
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

        foreach ($this->typeStore->getAll($bindingType->getName()) as $sameName) {
            $sameName->refreshState($this->typeStore);
        }
    }

    private function unloadBindingType($typeName, Package $package)
    {
        if ($this->typeStore->exists($typeName, $package)) {
            $this->typeStore->get($typeName, $package)->resetState();
            $this->typeStore->remove($typeName, $package);

            if ($this->typeStore->existsAny($typeName)) {
                foreach ($this->typeStore->getAll($typeName) as $sameName) {
                    $sameName->refreshState($this->typeStore);
                }
            }
        }
    }

    private function loadBindingTypeAndDefine(BindingTypeDescriptor $bindingType)
    {
        $typeName = $bindingType->getName();

        $enabledBefore = $this->typeStore->existsEnabled($typeName);

        $this->loadBindingType($bindingType, $this->rootPackage);

        // Type is now duplicated
        if ($enabledBefore) {
            $this->undefineType($bindingType);
            $this->reloadBindingAndUpdateForType($typeName);

            return;
        }

        // New type
        $this->defineType($bindingType);
        $this->reloadBindingAndUpdateForType($typeName, BindingState::HELD_BACK);
    }

    private function unloadBindingTypeAndUndefine($typeName)
    {
        $this->unloadBindingType($typeName, $this->rootPackage);

        // Type was removed entirely
        if (!$this->typeStore->existsAny($typeName)) {
            $this->undefineType($typeName);
            $this->reloadBindingAndUpdateForType($typeName);

            return;
        }

        // Duplication was resolved
        if (!$this->typeStore->isDuplicate($typeName)) {
            $this->defineType($this->typeStore->get($typeName));
            $this->reloadBindingAndUpdateForType($typeName, BindingState::IGNORED);
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

        $binding->refreshState($package, $this->typeStore);
    }

    private function unloadBinding(Uuid $uuid, Package $package)
    {
        if ($this->bindingStore->exists($uuid, $package)) {
            $this->bindingStore->get($uuid, $package)->resetState();
            $this->bindingStore->remove($uuid, $package);
        }
    }

    private function loadBindingAndBind(BindingDescriptor $binding)
    {
        $this->loadBinding($binding, $this->rootPackage);

        if (!$binding->isHeldBack() && !$binding->isIgnored()) {
            $this->assertBindingValid($binding);
        }

        if (!$this->bindingStore->isDuplicate($binding->getUuid()) && $binding->isEnabled()) {
            $this->bind($binding);
        }
    }

    private function unloadBindingAndUnbind(BindingDescriptor $binding)
    {
        if (!$this->bindingStore->isDuplicate($binding->getUuid())) {
            // unbind() must run before unload(), otherwise the binding's
            // reference to the type is lost
            $this->unbind($binding);
        }

        $this->unloadBinding($binding->getUuid(), $this->rootPackage);

    }

    private function reloadBindingAndUpdate(BindingDescriptor $binding, Package $package)
    {
        $enabledBefore = $this->bindingStore->existsEnabled($binding->getUuid());

        $binding->refreshState($package, $this->typeStore);

        $enabledAfter = $this->bindingStore->existsEnabled($binding->getUuid());

        if (!$enabledBefore && $enabledAfter) {
            $this->bind($binding);
        } elseif ($enabledBefore && !$enabledAfter) {
            $this->unbind($binding);
        }
    }

    private function reloadBindingAndUpdateForType($typeName, $state = null)
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

                $this->reloadBindingAndUpdate($binding, $package);
            }
        }
    }

    /**
     * @param BindingTypeDescriptor $bindingType
     */
    private function defineType(BindingTypeDescriptor $bindingType)
    {
        $this->discovery->defineType($bindingType->toBindingType());
    }

    /**
     * @param $typeName
     */
    private function undefineType($typeName)
    {
        $this->discovery->undefineType($typeName);
    }

    /**
     * @param $binding
     */
    private function bind(BindingDescriptor $binding)
    {
        $this->discovery->bind(
            $binding->getQuery(),
            $binding->getTypeName(),
            $binding->getParameterValues(),
            $binding->getLanguage()
        );
    }

    /**
     * @param $binding
     */
    private function unbind(BindingDescriptor $binding)
    {
        $this->discovery->unbind(
            $binding->getQuery(),
            $binding->getTypeName(),
            $binding->getParameterValues(),
            $binding->getLanguage()
        );
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
