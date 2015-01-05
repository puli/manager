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
    private $bindings = array();

    /**
     * @var bool[][]
     */
    private $packageNamesByType = array();

    /**
     * @var bool[][]
     */
    private $packageNamesByBinding = array();

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

        $typeName = $bindingType->getName();

        if (isset($this->bindingTypes[$typeName])) {
            throw DuplicateTypeException::forTypeName($typeName);
        }

        $this->rootPackageFile->addTypeDescriptor($bindingType);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        $this->loadBindingType($bindingType, $this->rootPackage->getName());

        $this->defineType($bindingType);
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

        $this->rootPackageFile->removeTypeDescriptor($typeName);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        $this->unloadBindingType($typeName, $this->rootPackage->getName());

        // If the type is still loaded, that means it is also defined by an
        // installed package
        if (isset($this->bindingTypes[$typeName])) {
            $this->defineTypeUnlessDisabled($this->bindingTypes[$typeName]);

            return;
        }

        $this->undefineType($typeName);
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

        if (!isset($this->bindingTypes[$typeName])) {
            throw NoSuchTypeException::forTypeName($typeName);
        }

        $binding = BindingDescriptor::create($query, $typeName, $parameters, $language);
        $uuid = $binding->getUuid();

        if ($this->rootPackageFile->hasBindingDescriptor($uuid)) {
            return;
        }

        $this->rootPackageFile->addBindingDescriptor($binding);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        if (isset($this->bindings[$uuid->toString()])) {
            return;
        }

        $this->loadBinding($binding, $this->rootPackage->getName());

        $this->discovery->bind($query, $typeName, $parameters, $language);
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

        $this->rootPackageFile->removeBindingDescriptor($uuid);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        // Remember binding before removing it
        $binding = $this->bindings[$uuid->toString()];

        $this->unloadBinding($uuid, $this->rootPackage->getName());

        // If the binding is still loaded, that means it is also defined by an
        // installed package
        if (isset($this->bindings[$uuid->toString()])) {
            return;
        }

        $this->unbind($binding);
    }

    /**
     * Returns all bindings.
     *
     * You can optionally filter types by one or multiple package names.
     *
     * @param string|string[] $packageName The package name(s) to filter by.
     *
     * @return BindingDescriptor[] The bindings.
     */
    public function getBindings($packageName = null)
    {
        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $bindings = array();

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();

            foreach ($packageFile->getBindingDescriptors() as $binding) {
                $bindings[$binding->getUuid()->toString()] = $binding;
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
            $this->defineTypeUnlessDisabled($typeDescriptor);
        }

        foreach ($this->bindings as $bindingDescriptor) {
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
        foreach ($this->packages as $package) {
            $this->loadPackage($package);
        }
    }

    /**
     * @param Package $package
     */
    private function loadPackage(Package $package)
    {
        $packageFile = $package->getPackageFile();
        $packageName = $package->getName();

        foreach ($packageFile->getTypeDescriptors() as $typeDescriptor) {
            $this->loadBindingType($typeDescriptor, $packageName);
        }

        foreach ($packageFile->getBindingDescriptors() as $bindingDescriptor) {
            $installInfo = $package->getInstallInfo();
            $uuid = $bindingDescriptor->getUuid();

            if ($package instanceof RootPackage || $installInfo->hasEnabledBindingUuid($uuid)) {
                $this->loadBinding($bindingDescriptor, $packageName);
            }
        }
    }

    /**
     * @param BindingTypeDescriptor $bindingType
     * @param                       $packageName
     */
    private function loadBindingType(BindingTypeDescriptor $bindingType, $packageName)
    {
        $typeName = $bindingType->getName();

        if (isset($this->bindingTypes[$typeName])) {
            $packageNames = array_keys($this->packageNamesByType[$typeName]);

            $this->logger->warning(sprintf(
                'The packages "%s" and "%s" contain type definitions for '.
                'the same type "%s". The type has been disabled.',
                implode('", "', $packageNames),
                $packageName,
                $typeName
            ));

            $this->packageNamesByType[$typeName][$packageName] = true;

            return;
        }

        $this->bindingTypes[$typeName] = $bindingType;
        $this->packageNamesByType[$typeName] = array($packageName => true);
    }

    /**
     * @param $typeName
     * @param $packageName
     */
    private function unloadBindingType($typeName, $packageName)
    {
        unset($this->packageNamesByType[$typeName][$packageName]);

        // If this was the only package referencing the type, remove it
        // completely
        if (0 === count($this->packageNamesByType[$typeName])) {
            unset($this->bindingTypes[$typeName]);
            unset($this->packageNamesByType[$typeName]);
        }
    }

    /**
     * @param BindingTypeDescriptor $typeDescriptor
     */
    private function defineTypeUnlessDisabled(BindingTypeDescriptor $typeDescriptor)
    {
        if (1 === count($this->packageNamesByType[$typeDescriptor->getName()])) {
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
     * @param                   $packageName
     */
    private function loadBinding(BindingDescriptor $binding, $packageName)
    {
        $uuidString = $binding->getUuid()->toString();

        if (!isset($this->packageNamesByBinding[$uuidString])) {
            $this->packageNamesByBinding[$uuidString] = array();
        }

        $this->bindings[$uuidString] = $binding;
        $this->packageNamesByBinding[$uuidString][$packageName] = true;
    }

    private function unloadBinding(Uuid $uuid, $packageName)
    {
        $uuidString = $uuid->toString();

        unset($this->packageNamesByBinding[$uuidString][$packageName]);

        if (0 === count($this->packageNamesByBinding[$uuidString])) {
            unset($this->bindings[$uuidString]);
            unset($this->packageNamesByBinding[$uuidString]);
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
}
