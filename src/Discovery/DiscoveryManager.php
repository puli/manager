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
     * @var BindingTypeDescriptor[][]
     */
    private $bindingTypes = array();

    /**
     * @var BindingDescriptor[][]
     */
    private $bindings = array();

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
        $this->reloadAndRebindByType($bindingType->getName(), BindingState::HELD_BACK);
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
            $this->reloadAndRebindByType($typeName);

            return;
        }

        if (!$this->isDuplicateBindingType($typeName)) {
            $this->defineType($this->getBindingType($typeName));
            $this->reloadAndRebindByType($typeName, BindingState::IGNORED);
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

        if (!$this->isBindingTypeLoaded($typeName)) {
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

        if (!$binding = $this->getBinding($uuid, $this->rootPackage)) {
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
        if (!$this->discovery) {
            $this->loadDiscovery();
        }

        if (!$this->bindingTypes) {
            $this->loadPackages();
        }

        if (!$bindingsToEnable = $this->getBindingsToEnable($uuid, $packageName)) {
            return;
        }

        $wasDisabled = array();

        try {
            foreach ($bindingsToEnable as $packageName => $binding) {
                $installInfo = $this->packages[$packageName]->getInstallInfo();
                $wasDisabled[$uuid->toString()] = $installInfo->hasDisabledBindingUuid($uuid);
                $installInfo->addEnabledBindingUuid($uuid);
                $this->reloadAndRebind($binding, $this->packages[$packageName]);
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
                $this->reloadAndRebind($binding, $this->packages[$packageName]);
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
        if (!$this->bindingTypes) {
            $this->loadPackages();
        }

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
        if (!$this->discovery) {
            $this->loadDiscovery();
        }

        if (!$this->bindingTypes) {
            $this->loadPackages();
        }

        if (count($this->discovery->getBindings()) > 0 || count($this->discovery->getTypes()) > 0) {
            throw new DiscoveryNotEmptyException('The discovery is not empty.');
        }

        foreach ($this->bindingTypes as $typeName => $typesByPackage) {
            $type = reset($typesByPackage);

            $this->defineTypeUnlessDuplicate($type);
        }

        foreach ($this->bindings as $uuidString => $bindingsByPackage) {
            // All bindings with the same UUID have the same contents, so we
            // only need to bind the first one
            $binding = reset($bindingsByPackage);

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

        if ($this->isBindingTypeLoaded($typeName)) {
            $packageNames = array_keys($this->bindingTypes[$typeName]);

            $this->logger->warning(sprintf(
                'The packages "%s" and "%s" contain type definitions for '.
                'the same type "%s". The type has been disabled.',
                implode('", "', $packageNames),
                $packageName,
                $typeName
            ));

            $this->bindingTypes[$typeName][$packageName] = $bindingType;

            return;
        }

        $this->bindingTypes[$typeName] = array($packageName => $bindingType);
    }

    /**
     * @param $typeName
     * @param $packageName
     */
    private function unloadBindingType($typeName, Package $package)
    {
        $packageName = $package->getName();

        unset($this->bindingTypes[$typeName][$packageName]);

        // If this was the only package referencing the type, remove it
        // completely
        if (0 === count($this->bindingTypes[$typeName])) {
            unset($this->bindingTypes[$typeName]);
        }
    }

    private function isBindingTypeLoaded($typeName)
    {
        return isset($this->bindingTypes[$typeName]);
    }

    private function getBindingType($typeName)
    {
        if (!isset($this->bindingTypes[$typeName])) {
            return null;
        }

        return reset($this->bindingTypes[$typeName]);
    }

    private function isDuplicateBindingType($typeName)
    {
        return isset($this->bindingTypes[$typeName]) &&
            1 !== count($this->bindingTypes[$typeName]);
    }

    private function isBindingLoaded(Uuid $uuid)
    {
        return isset($this->bindings[$uuid->toString()]);
    }

    private function isDuplicateBinding(Uuid $uuid)
    {
        $uuidString = $uuid->toString();

        return isset($this->bindings[$uuidString]) && 1 !== count($this->bindings[$uuidString]);
    }

    private function existsEnabledBinding(Uuid $uuid)
    {
        $uuidString = $uuid->toString();

        if (!isset($this->bindings[$uuidString])) {
            return false;
        }

        foreach ($this->bindings[$uuidString] as $binding) {
            if ($binding->isEnabled()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Uuid    $uuid
     * @param Package $package
     *
     * @return BindingDescriptor
     */
    private function getBinding(Uuid $uuid, Package $package)
    {
        $uuidString = $uuid->toString();
        $packageName = $package->getName();

        return isset($this->bindings[$uuidString][$packageName])
            ? $this->bindings[$uuidString][$packageName]
            : null;
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

        $binding->setState($this->detectBindingState($binding, $package));

        if (!isset($this->bindings[$uuidString])) {
            $this->bindings[$uuidString] = array();
        }

        $this->bindings[$uuidString][$packageName] = $binding;
    }

    private function unloadBinding(Uuid $uuid, Package $package)
    {
        $packageName = $package->getName();
        $uuidString = $uuid->toString();

        if (isset($this->bindings[$uuidString][$packageName])) {
            $this->bindings[$uuidString][$packageName]->setState(BindingState::UNLOADED);

            unset($this->bindings[$uuidString][$packageName]);

            if (0 === count($this->bindings[$uuidString])) {
                unset($this->bindings[$uuidString]);
            }
        }
    }

    private function reloadBinding(BindingDescriptor $binding, Package $package)
    {
        $binding->setState($this->detectBindingState($binding, $package));
    }

    /**
     * @param $binding
     */
    private function loadAndBind(BindingDescriptor $binding)
    {
        $this->loadBinding($binding, $this->rootPackage);

        if (!$this->isDuplicateBinding($binding->getUuid()) && $binding->isEnabled()) {
            $this->bind($binding);
        }
    }

    /**
     * @param $binding
     */
    private function unloadAndUnbind(BindingDescriptor $binding)
    {
        $this->unloadBinding($binding->getUuid(), $this->rootPackage);

        if (!$this->isBindingLoaded($binding->getUuid())) {
            $this->unbind($binding);
        }
    }

    private function reloadAndRebind(BindingDescriptor $binding, Package $package)
    {
        $enabledBefore = $this->existsEnabledBinding($binding->getUuid());
        $this->reloadBinding($binding, $package);
        $enabledAfter = $this->existsEnabledBinding($binding->getUuid());

        if (!$enabledBefore && $enabledAfter) {
            $this->bind($binding);
        } elseif ($enabledBefore && !$enabledAfter) {
            $this->unbind($binding);
        }
    }

    private function reloadAndRebindByType($typeName, $state = null)
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

                $this->reloadAndRebind($binding, $package);
            }
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
    private function detectBindingState(BindingDescriptor $bindingDescriptor, Package $package)
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

    /**
     * @param Uuid $uuid
     * @param      $packageName
     *
     * @return BindingDescriptor[]
     */
    private function getBindingsToEnable(Uuid $uuid, $packageName = null)
    {
        $rootPackageName = $this->rootPackage->getName();
        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $bindingsByPackage = $this->getBindingsByPackage($uuid, $packageNames);

        if (!$bindingsByPackage) {
            throw NoSuchBindingException::forUuid($uuid);
        }

        if (1 === count($bindingsByPackage) && isset($bindingsByPackage[$rootPackageName])) {
            throw CannotEnableBindingException::forUuid($uuid, $rootPackageName);
        }

        $bindingsToEnable = array();

        foreach ($bindingsByPackage as $packageName => $binding) {
            $installInfo = $this->packages[$packageName]->getInstallInfo();

            if ($installInfo->hasEnabledBindingUuid($uuid)) {
                continue;
            }

            if ($binding->isHeldBack()) {
                throw CannotEnableBindingException::forUuid(
                    $uuid,
                    $packageName,
                    'The type of the binding is not loaded.'
                );
            }

            if ($binding->isIgnored()) {
                throw CannotEnableBindingException::forUuid(
                    $uuid,
                    $packageName,
                    'The type of the binding is duplicated.'
                );
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
