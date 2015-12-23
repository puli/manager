<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Package;

use Exception;
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\InvalidConfigException;
use Puli\Manager\Api\NoDirectoryException;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\NameConflictException;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\PackageManager;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Package\UnsupportedVersionException;
use Puli\Manager\Assert\Assert;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;
use Webmozart\PathUtil\Path;

/**
 * Manages the package repository of a Puli project.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageManagerImpl implements PackageManager
{
    /**
     * @var ProjectContext
     */
    private $context;

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
     * @param ProjectContext     $context            The project context.
     * @param PackageFileStorage $packageFileStorage The package file storage.
     *
     * @throws FileNotFoundException  If the install path of a package not exist.
     * @throws NoDirectoryException   If the install path of a package points to a file.
     * @throws InvalidConfigException If a configuration file contains invalid configuration.
     * @throws NameConflictException  If a package has the same name as another loaded package.
     */
    public function __construct(ProjectContext $context, PackageFileStorage $packageFileStorage)
    {
        $this->context = $context;
        $this->rootDir = $context->getRootDirectory();
        $this->rootPackageFile = $context->getRootPackageFile();
        $this->packageFileStorage = $packageFileStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function installPackage($installPath, $name = null, $installerName = InstallInfo::DEFAULT_INSTALLER_NAME, $env = Environment::PROD)
    {
        Assert::string($installPath, 'The install path must be a string. Got: %s');
        Assert::string($installerName, 'The installer name must be a string. Got: %s');
        Assert::oneOf($env, Environment::all(), 'The environment must be one of: %2$s. Got: %s');
        Assert::nullOrPackageName($name);

        $this->assertPackagesLoaded();

        $installPath = Path::makeAbsolute($installPath, $this->rootDir);

        foreach ($this->packages as $package) {
            if ($installPath === $package->getInstallPath()) {
                return;
            }
        }

        if (null === $name && $packageFile = $this->loadPackageFile($installPath)) {
            // Read the name from the package file
            $name = $packageFile->getPackageName();
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
            throw NameConflictException::forName($name);
        }

        $relInstallPath = Path::makeRelative($installPath, $this->rootDir);
        $installInfo = new InstallInfo($name, $relInstallPath);
        $installInfo->setInstallerName($installerName);
        $installInfo->setEnvironment($env);

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
    public function renamePackage($name, $newName)
    {
        $package = $this->getPackage($name);

        if ($name === $newName) {
            return;
        }

        if ($this->packages->contains($newName)) {
            throw NameConflictException::forName($newName);
        }

        if ($package instanceof RootPackage) {
            $this->renameRootPackage($package, $newName);
        } else {
            $this->renameNonRootPackage($package, $newName);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removePackage($name)
    {
        // Only check that this is a string. The error message "not found" is
        // more helpful than e.g. "package name must contain /".
        Assert::string($name, 'The package name must be a string. Got: %s');

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
    public function removePackages(Expression $expr)
    {
        $this->assertPackagesLoaded();

        $installInfos = $this->rootPackageFile->getInstallInfos();
        $packages = $this->packages->toArray();

        foreach ($this->packages->getInstalledPackages() as $package) {
            if ($expr->evaluate($package)) {
                $this->rootPackageFile->removeInstallInfo($package->getName());
                $this->packages->remove($package->getName());
            }
        }

        if (!$installInfos) {
            return;
        }

        try {
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            $this->rootPackageFile->setInstallInfos($installInfos);
            $this->packages->replace($packages);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearPackages()
    {
        $this->removePackages(Expr::true());
    }

    /**
     * {@inheritdoc}
     */
    public function getPackage($name)
    {
        Assert::string($name, 'The package name must be a string. Got: %s');

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
    public function findPackages(Expression $expr)
    {
        $this->assertPackagesLoaded();

        $packages = new PackageCollection();

        foreach ($this->packages as $package) {
            if ($expr->evaluate($package)) {
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
        Assert::string($name, 'The package name must be a string. Got: %s');

        $this->assertPackagesLoaded();

        return $this->packages->contains($name);
    }

    /**
     * {@inheritdoc}
     */
    public function hasPackages(Expression $expr = null)
    {
        $this->assertPackagesLoaded();

        if (!$expr) {
            return !$this->packages->isEmpty();
        }

        foreach ($this->packages as $package) {
            if ($expr->evaluate($package)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Loads all packages referenced by the install file.
     *
     * @throws FileNotFoundException  If the install path of a package not exist.
     * @throws NoDirectoryException   If the install path of a package points to a
     *                                file.
     * @throws InvalidConfigException If a package is not configured correctly.
     * @throws NameConflictException  If a package has the same name as another
     *                                loaded package.
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
     * @return PackageFile|null The loaded package file or `null` if none
     *                          could be found.
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

        try {
            return $this->packageFileStorage->loadPackageFile($installPath.'/puli.json');
        } catch (FileNotFoundException $e) {
            // Packages without package files are ok
            return null;
        }
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

    private function renameRootPackage(RootPackage $package, $newName)
    {
        $packageFile = $package->getPackageFile();
        $previousName = $packageFile->getPackageName();
        $packageFile->setPackageName($newName);

        try {
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            $packageFile->setPackageName($previousName);

            throw $e;
        }

        $this->packages->remove($package->getName());
        $this->packages->add(new RootPackage($packageFile, $package->getInstallPath()));
    }

    private function renameNonRootPackage(Package $package, $newName)
    {
        $previousInstallInfo = $package->getInstallInfo();

        $installInfo = new InstallInfo($newName, $previousInstallInfo->getInstallPath());
        $installInfo->setInstallerName($previousInstallInfo->getInstallerName());

        foreach ($previousInstallInfo->getDisabledBindingUuids() as $uuid) {
            $installInfo->addDisabledBindingUuid($uuid);
        }

        $this->rootPackageFile->removeInstallInfo($package->getName());
        $this->rootPackageFile->addInstallInfo($installInfo);

        try {
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            $this->rootPackageFile->removeInstallInfo($newName);
            $this->rootPackageFile->addInstallInfo($previousInstallInfo);

            throw $e;
        }

        $this->packages->remove($package->getName());
        $this->packages->add(new Package(
            $package->getPackageFile(),
            $package->getInstallPath(),
            $installInfo,
            $package->getLoadErrors()
        ));
    }
}
