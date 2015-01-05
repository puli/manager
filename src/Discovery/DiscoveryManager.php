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

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DiscoveryManager
{
    /**
     * Marks disabled binding types.
     */
    const TYPE_DISABLED = -1;

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
     * @var string[]
     */
    private $packageNameByType = array();

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

        $this->discovery->define($bindingType->toBindingType());
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

        $this->unloadBindingType($typeName);

        $this->discovery->undefine($typeName);
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
            if (self::TYPE_DISABLED !== $typeDescriptor) {
                $this->discovery->define($typeDescriptor->toBindingType());
            }
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
        $packageName = $package->getName();

        foreach ($packageFile->getTypeDescriptors() as $typeDescriptor) {
            $this->loadBindingType($typeDescriptor, $packageName);
        }

        foreach ($packageFile->getBindingDescriptors() as $bindingDescriptor) {
            $installInfo = $package->getInstallInfo();
            $uuid = $bindingDescriptor->getUuid();

            if ($package instanceof RootPackage || $installInfo->hasEnabledBindingUuid($uuid)) {
                $this->bindings[$uuid->toString()] = $bindingDescriptor;
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
            $this->logger->warning(sprintf(
                'The packages "%s" and "%s" contain type definitions for '.
                'the same type "%s". The type has been disabled.',
                $this->packageNameByType[$typeName],
                $packageName,
                $typeName
            ));

            $this->bindingTypes[$typeName] = self::TYPE_DISABLED;

            return;
        }

        $this->bindingTypes[$typeName] = $bindingType;
        $this->packageNameByType[$typeName] = $packageName;
    }

    /**
     * @param $typeName
     */
    private function unloadBindingType($typeName)
    {
        unset($this->bindingTypes[$typeName]);
        unset($this->packageNameByType[$typeName]);
    }
}
