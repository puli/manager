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

use Exception;
use Puli\RepositoryManager\Api\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Api\FileNotFoundException;
use Puli\RepositoryManager\Api\InvalidConfigException;
use Puli\RepositoryManager\Api\NoDirectoryException;
use Puli\RepositoryManager\Api\Package\InstallInfo;
use Puli\RepositoryManager\Api\Package\NameConflictException;
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Api\Package\PackageCollection;
use Puli\RepositoryManager\Api\Package\PackageFile;
use Puli\RepositoryManager\Api\Package\PackageManager;
use Puli\RepositoryManager\Api\Package\PackageState;
use Puli\RepositoryManager\Api\Package\RootPackage;
use Puli\RepositoryManager\Api\Package\RootPackageFile;
use Puli\RepositoryManager\Api\Package\UnsupportedVersionException;
use Puli\RepositoryManager\Assert\Assert;
use Webmozart\Criteria\Criteria;
use Webmozart\PathUtil\Path;

/**
 * Manages the package repository of a Puli project.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageManagerImpl implements PackageManager
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
     * Loads the package repository for a given project.
     *
     * @param ProjectEnvironment $environment        The project environment.
     * @param PackageFileStorage $packageFileStorage The package file storage.
     *
     * @throws FileNotFoundException If the install path of a package not exist.
     * @throws NoDirectoryException If the install path of a package points to a file.
     * @throws InvalidConfigException If a configuration file contains invalid configuration.
     * @throws NameConflictException If a package has the same name as another loaded package.
     */
    public function __construct(ProjectEnvironment $environment, PackageFileStorage $packageFileStorage)
    {
        $this->environment = $environment;
        $this->rootDir = $environment->getRootDirectory();
        $this->rootPackageFile = $environment->getRootPackageFile();
        $this->packageFileStorage = $packageFileStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function installPackage($installPath, $name = null, $installerName = InstallInfo::DEFAULT_INSTALLER_NAME)
    {
        Assert::string($installPath, 'The install path must be a string.');
        Assert::string($installerName, 'The installer name must be a string.');
        Assert::nullOrPackageName($name);

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
        $installInfo->setInstallerName($installerName);

        $package = $this->loadPackage($installInfo);

        $this->assertNoLoadErrors($package);
        $this->rootPackageFile->addInstallInfo($installInfo);

        try {
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            $this->rootPackageFile->removeInstallInfo($name);

            throw $e;
        }

        $this->packages->add($package);

    }

    /**
     * {@inheritdoc}
     */
    public function isPackageInstalled($installPath)
    {
        Assert::string($installPath, 'The install path must be a string.');

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
     * {@inheritdoc}
     */
    public function removePackage($name)
    {
        // Only check that this is a string. The error message "not found" is
        // more helpful than e.g. "package name must contain /".
        Assert::string($name, 'The package name must be a string');

        $this->assertPackagesLoaded();

        if ($this->rootPackageFile->hasInstallInfo($name)) {
            $installInfo = $this->rootPackageFile->getInstallInfo($name);
            $this->rootPackageFile->removeInstallInfo($name);

            try {
                $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
            } catch (Exception $e) {
                $this->rootPackageFile->addInstallInfo($installInfo);

                throw $e;
            }
        }

        $this->packages->remove($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getPackage($name)
    {
        Assert::string($name, 'The package name must be a string');

        $this->assertPackagesLoaded();

        return $this->packages->get($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getRootPackage()
    {
        $this->assertPackagesLoaded();

        return $this->packages->getRootPackage();
    }

    /**
     * {@inheritdoc}
     */
    public function getPackages()
    {
        $this->assertPackagesLoaded();

        // Never return he original collection
        return clone $this->packages;
    }

    /**
     * {@inheritdoc}
     */
    public function findPackages(Criteria $criteria)
    {
        $this->assertPackagesLoaded();

        $packages = new PackageCollection();

        foreach ($this->packages as $package) {
            if ($package->match($criteria)) {
                $packages->add($package);
            }
        }

        return $packages;
    }

    /**
     * {@inheritdoc}
     */
    public function hasPackage($name)
    {
        Assert::string($name, 'The package name must be a string');

        $this->assertPackagesLoaded();

        return $this->packages->contains($name);
    }

    /**
     * {@inheritdoc}
     */
    public function hasPackages(Criteria $criteria = null)
    {
        $this->assertPackagesLoaded();

        if (!$criteria) {
            return !$this->packages->isEmpty();
        }

        foreach ($this->packages as $package) {
            if ($package->match($criteria)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
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
    private function loadPackages()
    {
        $this->packages = new PackageCollection();
        $this->packages->add(new RootPackage($this->rootPackageFile, $this->rootDir));

        foreach ($this->rootPackageFile->getInstallInfos() as $installInfo) {
            // Catch and log exceptions so that single packages cannot break
            // the whole repository
            $this->packages->add($this->loadPackage($installInfo));
        }
    }

    /**
     * Loads a package for the given install info.
     *
     * @param InstallInfo $installInfo The install info.
     *
     * @return Package The package.
     */
    private function loadPackage(InstallInfo $installInfo)
    {
        $installPath = Path::makeAbsolute($installInfo->getInstallPath(), $this->rootDir);
        $packageFile = null;
        $loadError = null;

        try {
            $packageFile = $this->loadPackageFile($installPath);
        } catch (InvalidConfigException $loadError) {
        } catch (UnsupportedVersionException $loadError) {
        } catch (FileNotFoundException $loadError) {
        } catch (NoDirectoryException $loadError) {
        }

        $loadErrors = $loadError ? array($loadError) : array();

        return new Package($packageFile, $installPath, $installInfo, $loadErrors);
    }

    /**
     * Loads the package file for the package at the given install path.
     *
     * @param string $installPath The absolute install path of the package
     *
     * @return PackageFile The loaded package file.
     */
    private function loadPackageFile($installPath)
    {
        if (!file_exists($installPath)) {
            throw FileNotFoundException::forPath($installPath);
        }

        if (!is_dir($installPath)) {
            throw new NoDirectoryException(sprintf(
                'The path %s is a file. Expected a directory.',
                $installPath
            ));
        }

        return $this->packageFileStorage->loadPackageFile($installPath.'/puli.json');
    }

    private function assertPackagesLoaded()
    {
        if (!$this->packages) {
            $this->loadPackages();
        }
    }

    private function assertNoLoadErrors(Package $package)
    {
        $loadErrors = $package->getLoadErrors();

        if (count($loadErrors) > 0) {
            // Rethrow first error
            throw reset($loadErrors);
        }
    }
}
