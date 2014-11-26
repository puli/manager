<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Package;

use Puli\Filesystem\PhpCacheRepository;
use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\InvalidConfigException;
use Puli\PackageManager\NoDirectoryException;
use Puli\PackageManager\Package\Config\PackageConfigStorage;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\InstallFile\InstallFile;
use Puli\PackageManager\Package\InstallFile\InstallFileStorage;
use Puli\PackageManager\Package\InstallFile\PackageDescriptor;
use Puli\PackageManager\Package\Collection\NoSuchPackageException;
use Puli\PackageManager\Package\Collection\PackageCollection;
use Puli\PackageManager\Project\ProjectEnvironment;
use Puli\PackageManager\Repository\ResourceConflictException;
use Puli\PackageManager\Repository\ResourceDefinitionException;
use Puli\PackageManager\Repository\RepositoryBuilder;
use Puli\Repository\ResourceRepository;
use Puli\Util\Path;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Manages the package repository of a Puli project.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageManager
{
    /**
     * @var ProjectEnvironment
     */
    private $environment;

    /**
     * @var RootPackageConfig
     */
    private $rootPackageConfig;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var InstallFileStorage
     */
    private $installFileStorage;

    /**
     * @var PackageConfigStorage
     */
    private $packageConfigStorage;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var InstallFile
     */
    private $installFile;

    /**
     * Loads the package repository for a given project.
     *
     * @param ProjectEnvironment   $environment          The project environment.
     * @param PackageConfigStorage $packageConfigStorage The package config file storage.
     * @param InstallFileStorage   $installFileStorage   The install file storage.
     *
     * @throws FileNotFoundException If the install path of a package not exist.
     * @throws NoDirectoryException If the install path of a package points to a file.
     * @throws InvalidConfigException If a configuration file contains invalid configuration.
     * @throws NameConflictException If a package has the same name as another loaded package.
     */
    public function __construct(
        ProjectEnvironment $environment,
        PackageConfigStorage $packageConfigStorage,
        InstallFileStorage $installFileStorage
    )
    {
        $this->environment = $environment;
        $this->rootPackageConfig = $environment->getRootPackageConfig();
        $this->rootDir = $environment->getRootDirectory();
        $this->installFileStorage = $installFileStorage;
        $this->packageConfigStorage = $packageConfigStorage;
        $this->packages = new PackageCollection();

        $this->loadInstallFile();
        $this->loadPackages();
    }

    /**
     * Installs the package at the given path in the repository.
     *
     * @param string $installPath The path to the package.
     *
     * @throws FileNotFoundException If the package directory does not exist.
     * @throws NoDirectoryException If the package path points to a file.
     * @throws InvalidConfigException If the package is not configured correctly.
     * @throws NameConflictException If the package has the same name as another
     *                               loaded package.
     */
    public function installPackage($installPath)
    {
        $installPath = Path::makeAbsolute($installPath, $this->rootDir);

        if ($this->isPackageInstalled($installPath)) {
            return;
        }

        // Try to load the package
        $package = $this->loadPackage($installPath);

        // OK, now add it
        $relInstallPath = Path::makeRelative($installPath, $this->rootDir);
        $this->installFile->addPackageDescriptor(new PackageDescriptor($relInstallPath));
        $this->packages->add($package);

        $this->installFileStorage->saveInstallFile($this->installFile);
    }

    /**
     * Returns whether the package with the given path is installed.
     *
     * @param string $installPath The install path of the package.
     *
     * @return bool Whether that package is installed.
     */
    public function isPackageInstalled($installPath)
    {
        $installPath = Path::makeAbsolute($installPath, $this->rootDir);

        foreach ($this->packages as $package) {
            if ($installPath === $package->getInstallPath()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether the manager has the package with the given name.
     *
     * @param string $name The package name.
     *
     * @return bool Whether the manager has a package with that name.
     */
    public function hasPackage($name)
    {
        return $this->packages->contains($name);
    }

    /**
     * Returns a package by name.
     *
     * @param string $name The package name.
     *
     * @return Package The package.
     *
     * @throws NoSuchPackageException If the package was not found.
     */
    public function getPackage($name)
    {
        return $this->packages->get($name);
    }

    /**
     * Returns the root package.
     *
     * @return RootPackage The root package.
     */
    public function getRootPackage()
    {
        return $this->packages->getRootPackage();
    }

    /**
     * Returns all installed packages.
     *
     * @return PackageCollection The installed packages.
     */
    public function getPackages()
    {
        return $this->packages;
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
     * Returns the managed install file.
     *
     * @return InstallFile The install file.
     */
    public function getInstallFile()
    {
        return $this->installFile;
    }

    /**
     * Loads the install file into memory.
     *
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    private function loadInstallFile()
    {
        $path = $this->rootPackageConfig->getInstallFile();
        $path = Path::makeAbsolute($path, $this->rootDir);

        $this->installFile = $this->installFileStorage->loadInstallFile($path);
    }

    /**
     * Loads all packages referenced by the install file.
     *
     * @throws FileNotFoundException If the install path of a package not exist.
     * @throws NoDirectoryException If the install path of a package points to a
     *                              file.
     * @throws InvalidConfigException If a package is not configured correctly.
     * @throws NameConflictException If a package has the same name as another
     *                               loaded package.
     */
    private function loadPackages()
    {
        $this->packages->add(new RootPackage($this->rootPackageConfig, $this->rootDir));

        foreach ($this->installFile->getPackageDescriptors() as $packageDefinition) {
            $installPath = Path::makeAbsolute($packageDefinition->getInstallPath(), $this->rootDir);
            $package = $this->loadPackage($installPath);

            $this->packages->add($package);
        }
    }

    /**
     * Loads a package at a given install path.
     *
     * @param string $installPath The root directory of the package.
     *
     * @return Package The package.
     *
     * @throws FileNotFoundException If the install path does not exist.
     * @throws NoDirectoryException If the install path points to a file.
     * @throws NameConflictException If the package has the same name as another
     *                               loaded package.
     */
    private function loadPackage($installPath)
    {
        if (!file_exists($installPath)) {
            throw new FileNotFoundException(sprintf(
                'Could not load package: The directory %s does not exist.',
                $installPath
            ));
        }

        if (!is_dir($installPath)) {
            throw new NoDirectoryException(sprintf(
                'Could not install package: The path %s is a file. '.
                'Expected a directory.',
                $installPath
            ));
        }

        $config = $this->packageConfigStorage->loadPackageConfig($installPath.'/puli.json');
        $packageName = $config->getPackageName();

        if ($this->packages->contains($packageName)) {
            $conflictingPackage = $this->packages->get($packageName);

            throw new NameConflictException(sprintf(
                'Cannot load package "%s" at %s: The package at %s has the '.
                'same name.',
                $packageName,
                $installPath,
                $conflictingPackage->getInstallPath()
            ));
        }

        return new Package($config, $installPath);
    }
}
