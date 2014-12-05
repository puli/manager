<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package;

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\InvalidConfigException;
use Puli\RepositoryManager\NoDirectoryException;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\InstallFile\InstallFile;
use Puli\RepositoryManager\Package\InstallFile\InstallFileStorage;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Webmozart\PathUtil\Path;

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
     * @var string
     */
    private $rootDir;

    /**
     * @var InstallFileStorage
     */
    private $installFileStorage;

    /**
     * @var PackageFileStorage
     */
    private $packageFileStorage;

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
     * @param ProjectEnvironment $environment        The project environment.
     * @param PackageFileStorage $packageFileStorage The package file storage.
     * @param InstallFileStorage $installFileStorage The install file storage.
     *
     * @throws FileNotFoundException If the install path of a package not exist.
     * @throws NoDirectoryException If the install path of a package points to a file.
     * @throws InvalidConfigException If a configuration file contains invalid configuration.
     * @throws NameConflictException If a package has the same name as another loaded package.
     */
    public function __construct(
        ProjectEnvironment $environment,
        PackageFileStorage $packageFileStorage,
        InstallFileStorage $installFileStorage
    )
    {
        $this->environment = $environment;
        $this->rootDir = $environment->getRootDirectory();
        $this->installFileStorage = $installFileStorage;
        $this->packageFileStorage = $packageFileStorage;
        $this->packages = new PackageCollection();

        $this->loadInstallFile();
        $this->loadPackages();
    }

    /**
     * Installs the package at the given path in the repository.
     *
     * @param string      $installPath The path to the package.
     * @param string|null $name        The package name or `null` if the name
     *                                 should be read from the package's puli.json.
     * @param string      $installer   The name of the installer.
     *
     * @throws FileNotFoundException If the package directory does not exist.
     * @throws NoDirectoryException If the package path points to a file.
     * @throws InvalidConfigException If the package is not configured correctly.
     * @throws NameConflictException If the package has the same name as another
     *                               loaded package.
     */
    public function installPackage($installPath, $name = null, $installer = InstallInfo::DEFAULT_INSTALLER)
    {
        $installPath = Path::makeAbsolute($installPath, $this->rootDir);

        if ($this->isPackageInstalled($installPath)) {
            return;
        }

        // Try to load the package
        $relInstallPath = Path::makeRelative($installPath, $this->rootDir);
        $installInfo = new InstallInfo($relInstallPath);
        $installInfo->setPackageName($name);
        $installInfo->setInstaller($installer);
        $package = $this->loadPackage($installInfo);

        // OK, now add it
        $this->installFile->addInstallInfo($installInfo);
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
     * Removes the package with the given name.
     *
     * @param string $name The package name.
     */
    public function removePackage($name)
    {
        if (!$this->packages->contains($name)) {
            return;
        }

        $package = $this->packages->get($name);

        $this->packages->remove($name);

        if ($this->installFile->hasInstallInfo($package->getInstallPath())) {
            $this->installFile->removeInstallInfo($package->getInstallPath());
            $this->installFileStorage->saveInstallFile($this->installFile);
        }
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
     * Returns all packages installed by the given installer.
     *
     * @param string $installer The installer name.
     *
     * @return PackageCollection The packages.
     */
    public function getPackagesByInstaller($installer)
    {
        $packages = new PackageCollection();

        foreach ($this->packages as $package) {
            // The root package has no install info
            if ($package->getInstallInfo() && $installer === $package->getInstallInfo()->getInstaller()) {
                $packages->add($package);
            }
        }

        return $packages;
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
        $path = $this->environment->getConfig()->get(Config::INSTALL_FILE);
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
        $rootPackageFile = $this->environment->getRootPackageFile();

        $this->packages->add(new RootPackage($rootPackageFile, $this->rootDir));

        foreach ($this->installFile->getInstallInfos() as $installInfo) {
            $this->packages->add($this->loadPackage($installInfo));
        }
    }

    /**
     * Loads a package for the given install info.
     *
     * @param InstallInfo $installInfo The install info.
     *
     * @return Package The package.
     *
     * @throws FileNotFoundException If the install path does not exist.
     * @throws NoDirectoryException If the install path points to a file.
     * @throws NameConflictException If the package has the same name as another
     *                               loaded package.
     */
    private function loadPackage(InstallInfo $installInfo)
    {
        $installPath = Path::makeAbsolute($installInfo->getInstallPath(), $this->rootDir);

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

        $packageFile = $this->packageFileStorage->loadPackageFile($installPath.'/puli.json');
        $package = new Package($packageFile, $installPath, $installInfo);

        if (null === $package->getName()) {
            throw new InvalidConfigException(sprintf(
                'Could not find a name for the package at %s. The name should '.
                'either be set during installation or in the "name" property '.
                'in %s.',
                $installPath,
                $installPath.'/puli.json'
            ));
        }

        if ($this->packages->contains($package->getName())) {
            $conflictingPackage = $this->packages->get($package->getName());

            throw new NameConflictException(sprintf(
                'Cannot load package "%s" at %s: The package at %s has the '.
                'same name.',
                $package->getName(),
                $installPath,
                $conflictingPackage->getInstallPath()
            ));
        }

        return $package;
    }
}
