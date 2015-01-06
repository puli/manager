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
     * @var BindingTypeDescriptor[]
     */
    private $bindingTypes = array();

    /**
     * @var BindingDescriptor[]
     */
    private $enabledBindings = array();

    /**
     * @var BindingDescriptor[][][]
     */
    private $bindingsByState = array();

    /**
     * @var bool[][]
     */
    private $bindingTypeRefs = array();

    /**
     * @var bool[][]
     */
    private $enabledBindingRefs = array();

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
        if (!$this->discovery) {
            $this->loadDiscovery();
        }

        if (!$this->bindingTypes) {
            $this->loadPackages();
        }

        $this->loadBindingType($bindingType, $this->rootPackage);

        try {
            // First check that the type can be added without errors
            $this->defineType($bindingType);

            // Then save the configuration
            $this->rootPackageFile->addTypeDescriptor($bindingType);
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            // Clean up
            $this->rootPackageFile->removeTypeDescriptor($bindingType->getName());
            $this->unloadBindingType($bindingType->getName(), $this->rootPackage);
            $this->undefineType($bindingType->getName());

            throw $e;
        }

        // Add bindings that have been held back until now
        $this->reloadBindingsByType($bindingType->getName(), BindingState::HELD_BACK);
    }

    /**
     * Removes a binding type.
     *
     * @param string $typeName The name of the type to remove.
     */
    public function removeBindingType($typeName)
    {
        if (!$this->discovery) {
            $this->loadDiscovery();
        }

        if (!$this->bindingTypes) {
            $this->loadPackages();
        }

        if (!$this->rootPackageFile->hasTypeDescriptor($typeName)) {
            return;
        }

        // First remove from the configuration. If all else fails, the
        // discovery can be rebuilt completely with the deletion applied.
        $this->rootPackageFile->removeTypeDescriptor($typeName);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        $this->unloadBindingType($typeName, $this->rootPackage);

        if (!$this->isBindingTypeLoaded($typeName)) {
            $this->undefineType($typeName);
            $this->reloadBindingsByType($typeName);

            return;
        }

        if (!$this->isDuplicateBindingType($typeName)) {
            $this->defineType($this->bindingTypes[$typeName]);
            $this->reloadBindingsByType($typeName, BindingState::IGNORED);
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
        if (!$this->discovery) {
            $this->loadDiscovery();
        }

        if (!$this->bindingTypes) {
            $this->loadPackages();
        }

        $binding = BindingDescriptor::create($query, $typeName, $parameters, $language);

        if ($this->rootPackageFile->hasBindingDescriptor($binding->getUuid())) {
            return;
        }

        $this->loadBinding($binding, $this->rootPackage);

        try {
            // First check that the binding can actually be loaded and bound
            $this->bindUnlessDuplicate($binding);

            // If no error was detected, persist changes to the configuration
            $this->rootPackageFile->addBindingDescriptor($binding);
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            // Clean up
            $this->rootPackageFile->removeBindingDescriptor($binding->getUuid());
            $this->unloadBinding($binding->getUuid(), $this->rootPackage);
            $this->unbindUnlessStillReferenced($binding);

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
        if (!$this->discovery) {
            $this->loadDiscovery();
        }

        if (!$this->bindingTypes) {
            $this->loadPackages();
        }

        if (!$this->rootPackageFile->hasBindingDescriptor($uuid)) {
            return;
        }

        // First remove from the configuration. If all else fails, the
        // discovery can be rebuilt completely with the deletion applied.
        $this->rootPackageFile->removeBindingDescriptor($uuid);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        // The binding is not enabled if the binding type was not loaded
        if (!$this->isBindingEnabled($uuid)) {
            return;
        }

        // Remember the binding before removing it
        $binding = $this->enabledBindings[$uuid->toString()];

        $this->unloadBinding($uuid, $this->rootPackage);
        $this->unbindUnlessStillReferenced($binding);
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
        if (!$this->bindingTypes) {
            $this->loadPackages();
        }

        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $bindings = array();

        foreach ($packageNames as $packageName) {
            if (!isset($this->bindingsByState[$packageName][$state])) {
                continue;
            }

            foreach ($this->bindingsByState[$packageName][$state] as $uuidString => $binding) {
                $bindings[$uuidString] = $binding;
            }
        }

        return array_values($bindings);
    }

    /**
     * Returns all bindings with the given UUID prefix.
     *
     * @param string          $uuidPrefix  The UUID prefix to search.
     * @param string|string[] $packageName The package name(s) to filter by.
     *
     * @return BindingDescriptor[] The bindings.
     */
    public function findBindings($uuidPrefix, $packageName = null)
    {
        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $bindings = array();

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();

            foreach ($packageFile->getBindingDescriptors() as $binding) {
                $uuidString = $binding->getUuid()->toString();

                if (0 === strpos($uuidString, $uuidPrefix)) {
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
        if (!$this->discovery) {
            $this->loadDiscovery();
        }

        if (!$this->bindingTypes) {
            $this->loadPackages();
        }

        if (count($this->discovery->getBindings()) > 0 || count($this->discovery->getTypes()) > 0) {
            throw new DiscoveryNotEmptyException('The discovery is not empty.');
        }

        foreach ($this->bindingTypes as $typeDescriptor) {
            $this->defineTypeUnlessDuplicate($typeDescriptor);
        }

        foreach ($this->enabledBindings as $bindingDescriptor) {
            $this->bind($bindingDescriptor);
        }
    }

    /**
     * Clears all contents of the resource discovery.
     */
    public function clearDiscovery()
    {
        if (!$this->discovery) {
            $this->loadDiscovery();
        }

        $this->discovery->clear();
    }

    private function loadDiscovery()
    {
        $this->discovery = $this->environment->getDiscovery();
    }

    private function loadPackages()
    {
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
        foreach ($package->getPackageFile()->getTypeDescriptors() as $typeDescriptor) {
            $this->loadBindingType($typeDescriptor, $package);
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
     * @param BindingTypeDescriptor $bindingType
     * @param                       $packageName
     */
    private function loadBindingType(BindingTypeDescriptor $bindingType, Package $package)
    {
        $packageName = $package->getName();
        $typeName = $bindingType->getName();

        if (isset($this->bindingTypes[$typeName])) {
            $packageNames = array_keys($this->bindingTypeRefs[$typeName]);

            $this->logger->warning(sprintf(
                'The packages "%s" and "%s" contain type definitions for '.
                'the same type "%s". The type has been disabled.',
                implode('", "', $packageNames),
                $packageName,
                $typeName
            ));

            $this->bindingTypeRefs[$typeName][$packageName] = true;

            return;
        }

        $this->bindingTypes[$typeName] = $bindingType;
        $this->bindingTypeRefs[$typeName] = array($packageName => true);
    }

    /**
     * @param $typeName
     * @param $packageName
     */
    private function unloadBindingType($typeName, Package $package)
    {
        $packageName = $package->getName();

        unset($this->bindingTypeRefs[$typeName][$packageName]);

        // If this was the only package referencing the type, remove it
        // completely
        if (0 === count($this->bindingTypeRefs[$typeName])) {
            unset($this->bindingTypes[$typeName]);
            unset($this->bindingTypeRefs[$typeName]);
        }
    }

    private function isBindingTypeLoaded($typeName)
    {
        return isset($this->bindingTypes[$typeName]);
    }

    private function isDuplicateBindingType($typeName)
    {
        return isset($this->bindingTypeRefs[$typeName]) &&
            1 !== count($this->bindingTypeRefs[$typeName]);
    }

    private function isDuplicateBinding(Uuid $uuid)
    {
        $uuidString = $uuid->toString();

        return isset($this->enabledBindingRefs[$uuidString]) &&
            1 !== count($this->enabledBindingRefs[$uuidString]);
    }

    /**
     * @param Uuid $uuid
     *
     * @return bool
     */
    private function isBindingEnabled(Uuid $uuid)
    {
        return isset($this->enabledBindings[$uuid->toString()]);
    }

    /**
     * @param BindingTypeDescriptor $typeDescriptor
     */
    private function defineTypeUnlessDuplicate(BindingTypeDescriptor $typeDescriptor)
    {
        if (!$this->isDuplicateBindingType($typeDescriptor->getName())) {
            $this->defineType($typeDescriptor);
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
     * @param BindingDescriptor $binding
     * @param Package           $package
     */
    private function loadBinding(BindingDescriptor $binding, Package $package)
    {
        $packageName = $package->getName();
        $uuidString = $binding->getUuid()->toString();
        $state = $this->getBindingState($binding, $package);

        if (BindingState::ENABLED === $state) {
            if (!isset($this->enabledBindingRefs[$uuidString])) {
                $this->enabledBindingRefs[$uuidString] = array();
            }

            $this->enabledBindings[$uuidString] = $binding;
            $this->enabledBindingRefs[$uuidString][$packageName] = true;
        }

        if (!isset($this->bindingsByState[$packageName])) {
            $this->bindingsByState[$packageName] = array();
        }

        if (!isset($this->bindingsByState[$packageName][$state])) {
            $this->bindingsByState[$packageName][$state] = array();
        }

        $this->bindingsByState[$packageName][$state][$uuidString] = $binding;
    }

    private function unloadBinding(Uuid $uuid, Package $package)
    {
        $packageName = $package->getName();
        $uuidString = $uuid->toString();

        if (isset($this->enabledBindings[$uuidString])) {
            unset($this->enabledBindingRefs[$uuidString][$packageName]);

            if (0 === count($this->enabledBindingRefs[$uuidString])) {
                unset($this->enabledBindings[$uuidString]);
                unset($this->enabledBindingRefs[$uuidString]);
            }
        }

        foreach ($this->bindingsByState[$packageName] as $state => $bindings) {
            unset($this->bindingsByState[$packageName][$state][$uuidString]);

            if (0 === count($this->bindingsByState[$packageName][$state])) {
                unset($this->bindingsByState[$packageName][$state]);
            }
        }

        if (0 === count($this->bindingsByState[$packageName])) {
            unset($this->bindingsByState[$packageName]);
        }
    }

    private function reloadBinding(BindingDescriptor $binding, Package $package)
    {
        $enabledBefore = $this->isBindingEnabled($binding->getUuid());
        $this->unloadBinding($binding->getUuid(), $package);
        $this->loadBinding($binding, $package);
        $enabledAfter = $this->isBindingEnabled($binding->getUuid());

        if (!$enabledBefore && $enabledAfter) {
            $this->bind($binding);
        } elseif ($enabledBefore && !$enabledAfter) {
            $this->unbind($binding);
        }
    }

    private function reloadBindingsByType($typeName, $stateFilter = null)
    {
        $states = $stateFilter ? array($stateFilter) : BindingState::all();

        foreach ($this->bindingsByState as $packageName => $bindingsByState) {
            foreach ($states as $state) {
                if (!isset($this->bindingsByState[$packageName][$state])) {
                    continue;
                }

                $bindings = $this->bindingsByState[$packageName][$state];

                foreach ($bindings as $uuidString => $binding) {
                    if ($typeName !== $binding->getTypeName()) {
                        continue;
                    }

                    $this->reloadBinding($binding, $this->packages[$packageName]);
                }
            }
        }
    }

    private function bindUnlessDuplicate(BindingDescriptor $binding)
    {
        if (!$this->isDuplicateBinding($binding->getUuid())) {
            $this->bind($binding);
        }
    }

    private function unbindUnlessStillReferenced(BindingDescriptor $binding)
    {
        if (!$this->isBindingEnabled($binding->getUuid())) {
            $this->unbind($binding);
        }
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
     * @param BindingDescriptor $bindingDescriptor
     * @param Package           $package
     *
     * @return int
     */
    private function getBindingState(BindingDescriptor $bindingDescriptor, Package $package)
    {
        $installInfo = $package->getInstallInfo();
        $uuid = $bindingDescriptor->getUuid();
        $typeName = $bindingDescriptor->getTypeName();

        if (!$this->isBindingTypeLoaded($typeName)) {
            return BindingState::HELD_BACK;
        }

        if ($this->isDuplicateBindingType($typeName)) {
            return BindingState::IGNORED;
        }

        if (!$package instanceof RootPackage && $installInfo->hasDisabledBindingUuid($uuid)) {
            return BindingState::DISABLED;
        }

        if (!$package instanceof RootPackage && !$installInfo->hasEnabledBindingUuid($uuid)) {
            return BindingState::UNDECIDED;
        }

        return BindingState::ENABLED;
    }
}
