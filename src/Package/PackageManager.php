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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\InvalidConfigException;
use Puli\RepositoryManager\NoDirectoryException;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\Reader\UnsupportedVersionException;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
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
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Loads the package repository for a given project.
     *
     * @param ProjectEnvironment $environment        The project environment.
     * @param PackageFileStorage $packageFileStorage The package file storage.
     * @param LoggerInterface    $logger             The used logger.
     *
     * @throws FileNotFoundException If the install path of a package not exist.
     * @throws NoDirectoryException If the install path of a package points to a file.
     * @throws InvalidConfigException If a configuration file contains invalid configuration.
     * @throws NameConflictException If a package has the same name as another loaded package.
     */
    public function __construct(
        ProjectEnvironment $environment,
        PackageFileStorage $packageFileStorage,
        LoggerInterface $logger = null
    )
    {
        $this->environment = $environment;
        $this->rootDir = $environment->getRootDirectory();
        $this->rootPackageFile = $environment->getRootPackageFile();
        $this->packageFileStorage = $packageFileStorage;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * Installs the package at the given path in the repository.
     *
     * @param string      $installPath The path to the package.
     * @param string|null $name        The package name or `null` if the name
     *                                 should be read from the package's puli.json.
     * @param string      $installer   The name of the installer.
     *
     * @throws InvalidConfigException If the package is not configured correctly.
     * @throws NameConflictException If the package has the same name as another
     *                               loaded package.
     */
    public function installPackage($installPath, $name = null, $installer = InstallInfo::DEFAULT_INSTALLER)
    {
        $this->assertPackagesLoaded();

        $installPath = Path::makeAbsolute($installPath, $this->rootDir);

        if ($this->isPackageInstalled($installPath)) {
            return;
        }

        if (null === $name) {
            // Read the name from the package file
            $name = $this->loadPackageFile($installPath)->getPackageName();
        }

        if (null === $name) {
            throw new InvalidConfigException(sprintf(
                'Could not find a name for the package at %s. The name should '.
                'either be passed to the installer or be set in the "name" '.
                'property of %s.',
                $installPath,
                $installPath.'/puli.json'
            ));
        }

        if ($this->packages->contains($name)) {
            $conflictingPackage = $this->packages->get($name);

            throw new NameConflictException(sprintf(
                'Cannot load package "%s" at %s: The package at %s has the '.
                'same name.',
                $name,
                $installPath,
                $conflictingPackage->getInstallPath()
            ));
        }

        $relInstallPath = Path::makeRelative($installPath, $this->rootDir);
        $installInfo = new InstallInfo($name, $relInstallPath);
        $installInfo->setInstaller($installer);
        $package = $this->loadPackage($installInfo);

        // OK, now add it
        $this->rootPackageFile->addInstallInfo($installInfo);
        $this->packages->add($package);

        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
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
        $this->assertPackagesLoaded();

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
        $this->assertPackagesLoaded();

        if (!$this->packages->contains($name)) {
            return;
        }

        $this->packages->remove($name);

        if ($this->rootPackageFile->hasInstallInfo($name)) {
            $this->rootPackageFile->removeInstallInfo($name);
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
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
        $this->assertPackagesLoaded();

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
        $this->assertPackagesLoaded();

        return $this->packages->get($name);
    }

    /**
     * Returns the root package.
     *
     * @return RootPackage The root package.
     */
    public function getRootPackage()
    {
        $this->assertPackagesLoaded();

        return $this->packages->getRootPackage();
    }

    /**
     * Returns all installed packages.
     *
     * @param int $state The state of the packages to return.
     *
     * @return PackageCollection The installed packages.
     */
    public function getPackages($state = PackageState::ENABLED)
    {
        $this->assertPackagesLoaded();

        $packages = new PackageCollection();

        foreach ($this->packages as $package) {
            if ($state === $package->getState()) {
                $packages[] = $package;
            }
        }

        return $packages;
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
        $this->assertPackagesLoaded();

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
     * Loads all packages referenced by the install file.
     *
     * @throws FileNotFoundException If the install path of a package not exist.
     * @throws NoDirectoryException If the install path of a package points to a
     *                              file.
     * @throws InvalidConfigException If a package is not configured correctly.
     * @throws NameConflictException If a package has the same name as another
     *                               loaded package.
     */
    public function loadPackages()
    {
        $this->packages = new PackageCollection();
        $this->packages->add(new RootPackage($this->rootPackageFile, $this->rootDir));

        foreach ($this->rootPackageFile->getInstallInfos() as $installInfo) {
            // Catch and log exceptions so that single packages cannot break
            // the whole repository
            $this->packages->add($this->loadPackage($installInfo, true));
        }
    }

    /**
     * Loads a package for the given install info.
     *
     * @param InstallInfo $installInfo   The install info.
     * @param bool        $logExceptions Whether to log exceptions occurring
     *                                   when reading the package file.
     *
     * @return Package The package.
     *
     * @throws FileNotFoundException If the install path does not exist.
     * @throws NoDirectoryException If the install path points to a file.
     * @throws NameConflictException If the package has the same name as another
     *                               loaded package.
     */
    private function loadPackage(InstallInfo $installInfo, $logExceptions = false)
    {
        $installPath = Path::makeAbsolute($installInfo->getInstallPath(), $this->rootDir);
        $packageFile = $this->loadPackageFile($installPath, $logExceptions);

        return new Package($packageFile, $installPath, $installInfo);
    }

    /**
     * Loads the package file for the package at the given install path.
     *
     * @param string $installPath The absolute install path of the package
     * @param bool   $logExceptions Whether to log exceptions occurring when
     *                              reading the package file.
     *
     * @return PackageFile|null The loaded package file or `null` if an
     *                          exception occurred and $logExceptions is set.
     */
    private function loadPackageFile($installPath, $logExceptions = false)
    {
        $e = null;

        try {
            if (!file_exists($installPath)) {
                throw new FileNotFoundException(sprintf(
                    'Could not load package: The directory %s does not exist.',
                    $installPath
                ));
            }

            if (!is_dir($installPath)) {
                throw new NoDirectoryException(sprintf(
                    'Could not load package: The path %s is a file. Expected a '.
                    'directory.',
                    $installPath
                ));
            }

            return $this->packageFileStorage->loadPackageFile($installPath.'/puli.json');
        } catch (InvalidConfigException $e) {
        } catch (UnsupportedVersionException $e) {
        } catch (FileNotFoundException $e) {
        } catch (NoDirectoryException $e) {
        }

        if (!$logExceptions) {
            throw $e;
        }

        $this->logger->warning($e->getMessage());

        return null;
    }

    private function assertPackagesLoaded()
    {
        if (!$this->packages) {
            $this->loadPackages();
        }
    }
}
