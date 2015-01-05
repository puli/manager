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

use Puli\Discovery\Api\DuplicateTypeException;
use Puli\Discovery\Api\EditableDiscovery;
use Puli\Discovery\Api\NoSuchTypeException;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\RootPackage;

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
     * Creates a tag manager.
     *
     * @param ProjectEnvironment $environment
     * @param PackageCollection  $packages
     * @param PackageFileStorage $packageFileStorage
     */
    public function __construct(ProjectEnvironment $environment, PackageCollection $packages, PackageFileStorage $packageFileStorage)
    {
        $this->environment = $environment;
        $this->packages = $packages;
        $this->packageFileStorage = $packageFileStorage;
        $this->rootPackageFile = $environment->getRootPackageFile();
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

        if (isset($this->bindingTypes[$bindingType->getName()])) {
            throw DuplicateTypeException::forTypeName($bindingType->getName());
        }

        $this->rootPackageFile->addTypeDescriptor($bindingType);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        $this->bindingTypes[$bindingType->getName()] = $bindingType;

        $this->discovery->define($bindingType->toBindingType());
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
                $types[] = $type;
            }
        }

        return $types;
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

        $this->rootPackageFile->addBindingDescriptor($binding);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        if (!isset($this->bindings[$uuid->toString()])) {
            $this->bindings[$uuid->toString()] = $binding;

            $this->discovery->bind($query, $typeName, $parameters, $language);
        }
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
                $bindings[] = $binding;
            }
        }

        return $bindings;
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

        foreach ($this->bindingTypes as $typeDescriptor) {
            $this->discovery->define($typeDescriptor->toBindingType());
        }

        foreach ($this->bindings as $bindingDescriptor) {
            $this->discovery->bind(
                $bindingDescriptor->getQuery(),
                $bindingDescriptor->getTypeName(),
                $bindingDescriptor->getParameters(),
                $bindingDescriptor->getLanguage()
            );
        }
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

        foreach ($packageFile->getTypeDescriptors() as $typeDescriptor) {
            $this->bindingTypes[$typeDescriptor->getName()] = $typeDescriptor;
        }

        foreach ($packageFile->getBindingDescriptors() as $bindingDescriptor) {
            $installInfo = $package->getInstallInfo();
            $uuid = $bindingDescriptor->getUuid();

            if ($package instanceof RootPackage || $installInfo->hasEnabledBindingUuid($uuid)) {
                $this->bindings[$uuid->toString()] = $bindingDescriptor;
            }
        }
    }
}
