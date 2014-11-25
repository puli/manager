<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Manager;

use Puli\Filesystem\PhpCacheRepository;
use Puli\PackageManager\Environment\ProjectEnvironment;
use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\InvalidConfigException;
use Puli\PackageManager\NoDirectoryException;
use Puli\PackageManager\Package\Config\PackageConfigStorage;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\Package;
use Puli\PackageManager\Package\RootPackage;
use Puli\PackageManager\Repository\Config\PackageDescriptor;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfigStorage;
use Puli\PackageManager\Repository\NoSuchPackageException;
use Puli\PackageManager\Repository\PackageRepository;
use Puli\PackageManager\Resource\ResourceConflictException;
use Puli\PackageManager\Resource\ResourceDefinitionException;
use Puli\PackageManager\Resource\ResourceRepositoryBuilder;
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
     * @var PackageRepositoryConfigStorage
     */
    private $repositoryConfigStorage;

    /**
     * @var PackageConfigStorage
     */
    private $packageConfigStorage;

    /**
     * @var PackageRepository
     */
    private $packageRepository;

    /**
     * @var PackageRepositoryConfig
     */
    private $repositoryConfig;

    /**
     * Loads the package repository for a given project.
     *
     * @param ProjectEnvironment             $environment             The project environment.
     * @param PackageConfigStorage           $packageConfigStorage    The package config file storage.
     * @param PackageRepositoryConfigStorage $repositoryConfigStorage The repository config file storage.
     *
     * @throws FileNotFoundException If the install path of a package not exist.
     * @throws NoDirectoryException If the install path of a package points to a file.
     * @throws InvalidConfigException If a configuration file contains invalid configuration.
     * @throws NameConflictException If a package has the same name as another loaded package.
     */
    public function __construct(
        ProjectEnvironment $environment,
        PackageConfigStorage $packageConfigStorage,
        PackageRepositoryConfigStorage $repositoryConfigStorage
    )
    {
        $this->environment = $environment;
        $this->rootPackageConfig = $environment->getRootPackageConfig();
        $this->rootDir = $environment->getRootDirectory();
        $this->repositoryConfigStorage = $repositoryConfigStorage;
        $this->packageConfigStorage = $packageConfigStorage;
        $this->packageRepository = new PackageRepository();

        $this->loadRepositoryConfig();
        $this->loadPackages();
    }

    /**
     * Generates a resource repository.
     *
     * Pass the path where the generated resource repository is placed in the
     * first argument. You can later `include` this path to retrieve the
     * repository:
     *
     * ```php
     * $packageManager->generatedResourceRepository('/path/to/repository.php');
     *
     * $repo = include '/path/to/repository.php';
     * ```
     *
     * In the second argument, you can pass the path where the cache files for
     * the generated resource repository are placed.
     *
     * If you don't pass any paths, the default values from the root package
     * configuration are taken.
     *
     * @param string|null $repoPath  The path to the generated resource
     *                               repository or `null` to use the configured
     *                               default path.
     * @param string|null $cachePath The path to the cache directory or `null`
     *                               to use the configured default path.
     *
     * @throws NoDirectoryException If the cache path exists and is not a
     *                              directory.
     * @throws ResourceConflictException If two packages contain conflicting
     *                                   resource definitions.
     * @throws ResourceDefinitionException If a resource definition is invalid.
     */
    public function generateResourceRepository($repoPath = null, $cachePath = null)
    {
        $repo = new ResourceRepository();
        $builder = new ResourceRepositoryBuilder();
        $repoPath = $repoPath ?: $this->rootPackageConfig->getGeneratedResourceRepository();
        $repoPath = Path::makeAbsolute($repoPath, $this->rootDir);
        $repoDir = Path::getDirectory($repoPath);
        $cachePath = $cachePath ?: $this->rootPackageConfig->getResourceRepositoryCache();
        $cachePath = Path::makeAbsolute($cachePath, $this->rootDir);
        $relCachePath = Path::makeRelative($cachePath, Path::getDirectory($repoPath));

        $builder->loadPackages($this->packageRepository);
        $builder->buildRepository($repo);

        if (is_dir($cachePath)) {
            $filesystem = new Filesystem();
            $filesystem->remove($cachePath);
        }

        PhpCacheRepository::dumpRepository($repo, $cachePath);

        if (!file_exists($repoDir)) {
            $filesystem = new Filesystem();
            $filesystem->mkdir($repoDir);
        }

        file_put_contents($repoPath, <<<EOF
<?php

// generated by the Puli package manager

use Puli\Filesystem\PhpCacheRepository;

return new PhpCacheRepository(__DIR__.'/$relCachePath');

EOF
        );
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
        $this->repositoryConfig->addPackageDescriptor(new PackageDescriptor($relInstallPath));
        $this->packageRepository->addPackage($package);

        $this->repositoryConfigStorage->saveRepositoryConfig($this->repositoryConfig);
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

        foreach ($this->packageRepository->getPackages() as $package) {
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
        return $this->packageRepository->containsPackage($name);
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
        return $this->packageRepository->getPackage($name);
    }

    /**
     * Returns the root package.
     *
     * @return RootPackage The root package.
     */
    public function getRootPackage()
    {
        return $this->packageRepository->getRootPackage();
    }

    /**
     * Returns all installed packages.
     *
     * @return Package[] The list of installed packages, indexed by their names.
     */
    public function getPackages()
    {
        return $this->packageRepository->getPackages();
    }

    /**
     * @return ProjectEnvironment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * @return PackageRepositoryConfig
     */
    public function getRepositoryConfig()
    {
        return $this->repositoryConfig;
    }

    /**
     * Loads the package repository configuration into memory.
     *
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    private function loadRepositoryConfig()
    {
        $configPath = $this->rootPackageConfig->getPackageRepositoryConfig();
        $configPath = Path::makeAbsolute($configPath, $this->rootDir);

        $this->repositoryConfig = $this->repositoryConfigStorage->loadRepositoryConfig($configPath);
    }

    /**
     * Loads all packages referenced by the package repository configuration.
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
        $this->packageRepository->addPackage(new RootPackage($this->rootPackageConfig, $this->rootDir));

        foreach ($this->repositoryConfig->getPackageDescriptors() as $packageDefinition) {
            $installPath = Path::makeAbsolute($packageDefinition->getInstallPath(), $this->rootDir);
            $package = $this->loadPackage($installPath);

            $this->packageRepository->addPackage($package);
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

        if ($this->packageRepository->containsPackage($packageName)) {
            $conflictingPackage = $this->packageRepository->getPackage($packageName);

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
