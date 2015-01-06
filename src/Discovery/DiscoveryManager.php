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
use Puli\Discovery\Api\DuplicateTypeException;
use Puli\Discovery\Api\EditableDiscovery;
use Puli\Discovery\Api\NoSuchTypeException;
use Puli\RepositoryManager\Discovery\Store\BindingStore;
use Puli\RepositoryManager\Discovery\Store\BindingTypeStore;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\Package;
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
    private $bindings;

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

        try {
            // First check that the type can be added without errors
            $this->typeStore->add($bindingType, $this->rootPackage);
            $this->defineType($bindingType);

            // Then save the configuration
            $this->rootPackageFile->addTypeDescriptor($bindingType);
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            // Clean up
            $this->rootPackageFile->removeTypeDescriptor($bindingType->getName());
            $this->typeStore->remove($bindingType->getName(), $this->rootPackage);
            $this->undefineType($bindingType->getName());

            throw $e;
        }

        // Add bindings that have been held back until now
        $this->reloadAndBindForType($bindingType->getName(), BindingState::HELD_BACK);
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

        $this->typeStore->remove($typeName, $this->rootPackage);

        if (!$this->typeStore->isDefined($typeName)) {
            $this->undefineType($typeName);
            $this->reloadAndBindForType($typeName);

            return;
        }

        if (!$this->typeStore->isDuplicate($typeName)) {
            $this->defineType($this->typeStore->get($typeName));
            $this->reloadAndBindForType($typeName, BindingState::IGNORED);
        }
    }

    /**
     * Returns all binding types.
     *
     * You can optionally filter types by one or multiple package names.
     *
     * @param string|string[] $packageName The package name(s) to filter by.
     *
     * @return BindingTypeDescriptor[] The binding types.
     */
    public function getBindingTypes($packageName = null)
    {
        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $types = array();

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();

            foreach ($packageFile->getTypeDescriptors() as $type) {
                $types[$type->getName()] = $type;
            }
        }

        return array_values($types);
    }

    /**
     * Adds a new binding.
     *
     * @param        $query
     * @param        $typeName
     * @param array  $parameters
     * @param string $language
     */
    public function addBinding($query, $typeName, array $parameters = array(), $language = 'glob')
    {
        $this->assertDiscoveryLoaded();
        $this->assertPackagesLoaded();

        if (!$this->typeStore->isDefined($typeName)) {
            throw NoSuchTypeException::forTypeName($typeName);
        }

        $binding = BindingDescriptor::create($query, $typeName, $parameters, $language);

        if ($this->rootPackageFile->hasBindingDescriptor($binding->getUuid())) {
            return;
        }

        try {
            // First check that the binding can actually be loaded and bound
            $this->loadAndBind($binding);

            // If no error was detected, persist changes to the configuration
            $this->rootPackageFile->addBindingDescriptor($binding);
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            // Clean up
            $this->unloadAndUnbind($binding);
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

        if (!$binding = $this->bindings->get($uuid, $this->rootPackage)) {
            return;
        }

        // The binding is not enabled if the binding type was not loaded
        if (!$binding->isEnabled()) {
            return;
        }

        $this->unloadAndUnbind($binding);
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
                $this->reloadAndBind($binding, $this->packages[$packageName]);
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
                $this->reloadAndBind($binding, $this->packages[$packageName]);
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

        if (count($this->discovery->getBindings()) > 0 || count($this->discovery->getTypes()) > 0) {
            throw new DiscoveryNotEmptyException('The discovery is not empty.');
        }

        foreach ($this->typeStore->getTypeNames() as $typeName) {
            if (!$this->typeStore->isDuplicate($typeName)) {
                $this->defineType($this->typeStore->get($typeName));
            }
        }

        foreach ($this->bindings->getUuids() as $uuid) {
            $binding = $this->bindings->get($uuid);

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

    private function loadDiscovery()
    {
        $this->discovery = $this->environment->getDiscovery();
    }

    private function loadPackages()
    {
        $this->typeStore = new BindingTypeStore();
        $this->bindings = new BindingStore();

        // First load all the types
        foreach ($this->packages as $package) {
            $this->loadTypesFromPackage($package);
        }

        // Then the bindings for the loaded types
        foreach ($this->packages as $package) {
            $this->loadBindingsFromPackage($package);
        }
    }

    /**
     * @param Package $package
     */
    private function loadTypesFromPackage(Package $package)
    {
        foreach ($package->getPackageFile()->getTypeDescriptors() as $bindingType) {
            $typeName = $bindingType->getName();

            if ($this->typeStore->isDefined($typeName)) {
                $this->logger->warning(sprintf(
                    'The packages "%s" and "%s" contain type definitions for '.
                    'the same type "%s". The type has been disabled.',
                    implode('", "', $this->typeStore->getDefiningPackageNames($typeName)),
                    $package->getName(),
                    $typeName
                ));
            }

            $this->typeStore->add($bindingType, $package);
        }
    }

    /**
     * @param Package $package
     */
    private function loadBindingsFromPackage(Package $package)
    {
        foreach ($package->getPackageFile()->getBindingDescriptors() as $bindingDescriptor) {
            $this->loadBinding($bindingDescriptor, $package);
        }
    }

    /**
     * @param BindingDescriptor $binding
     * @param Package           $package
     */
    private function loadBinding(BindingDescriptor $binding, Package $package)
    {
        $binding->refreshState($package, $this->typeStore);

        $this->bindings->add($binding, $package);
    }

    private function unloadBinding(Uuid $uuid, Package $package)
    {
        if ($this->bindings->exists($uuid, $package)) {
            $this->bindings->get($uuid, $package)->setState(BindingState::UNLOADED);
        }

        $this->bindings->remove($uuid, $package);
    }

    private function reloadBinding(BindingDescriptor $binding, Package $package)
    {
        $binding->refreshState($package, $this->typeStore);
    }

    /**
     * @param $binding
     */
    private function loadAndBind(BindingDescriptor $binding)
    {
        $this->loadBinding($binding, $this->rootPackage);

        if (!$this->bindings->isDuplicate($binding->getUuid()) && $binding->isEnabled()) {
            $this->bind($binding);
        }
    }

    /**
     * @param $binding
     */
    private function unloadAndUnbind(BindingDescriptor $binding)
    {
        $this->unloadBinding($binding->getUuid(), $this->rootPackage);

        if (!$this->bindings->existsAny($binding->getUuid())) {
            $this->unbind($binding);
        }
    }

    private function reloadAndBind(BindingDescriptor $binding, Package $package)
    {
        $enabledBefore = $this->bindings->existsEnabled($binding->getUuid());

        $this->reloadBinding($binding, $package);

        $enabledAfter = $this->bindings->existsEnabled($binding->getUuid());

        if (!$enabledBefore && $enabledAfter) {
            $this->bind($binding);
        } elseif ($enabledBefore && !$enabledAfter) {
            $this->unbind($binding);
        }
    }

    private function reloadAndBindForType($typeName, $state = null)
    {
        $states = $state ? array($state) : BindingState::all();

        foreach ($this->packages as $packageName => $package) {
            $packageFile = $package->getPackageFile();

            foreach ($packageFile->getBindingDescriptors() as $binding) {
                if (!in_array($binding->getState(), $states)) {
                    continue;
                }

                if ($typeName !== $binding->getTypeName()) {
                    continue;
                }

                $this->reloadAndBind($binding, $package);
            }
        }
    }

    /**
     * @param BindingTypeDescriptor $bindingType
     */
    private function defineType(BindingTypeDescriptor $bindingType)
    {
        $this->discovery->define($bindingType->toBindingType());
    }

    /**
     * @param $typeName
     */
    private function undefineType($typeName)
    {
        $this->discovery->undefine($typeName);
    }

    /**
     * @param $binding
     */
    private function bind(BindingDescriptor $binding)
    {
        $this->discovery->bind(
            $binding->getQuery(),
            $binding->getTypeName(),
            $binding->getParameters(),
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
            $binding->getParameters(),
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
