<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager;

use Puli\Filesystem\PhpCacheRepository;
use Puli\PackageManager\Package\Config\Reader\PackageConfigReaderInterface;
use Puli\PackageManager\Package\Config\Reader\PackageJsonReader;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\Config\Writer\PackageConfigWriterInterface;
use Puli\PackageManager\Package\Config\Writer\PackageJsonWriter;
use Puli\PackageManager\Package\Package;
use Puli\PackageManager\Package\RootPackage;
use Puli\PackageManager\Plugin\PluginInterface;
use Puli\PackageManager\Repository\Config\PackageDescriptor;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;
use Puli\PackageManager\Repository\Config\Reader\RepositoryConfigReaderInterface;
use Puli\PackageManager\Repository\Config\Reader\RepositoryJsonReader;
use Puli\PackageManager\Repository\Config\Writer\RepositoryConfigWriterInterface;
use Puli\PackageManager\Repository\Config\Writer\RepositoryJsonWriter;
use Puli\PackageManager\Repository\PackageRepository;
use Puli\PackageManager\Resource\ResourceConflictException;
use Puli\PackageManager\Resource\ResourceDefinitionException;
use Puli\PackageManager\Resource\ResourceRepositoryBuilder;
use Puli\Repository\ResourceRepository;
use Puli\Resource\NoDirectoryException;
use Puli\Util\Path;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Manages the package repository.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageManager
{
    /**
     * The name of the Puli package config file.
     */
    const PACKAGE_CONFIG = 'puli.json';

    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    /**
     * @var RepositoryConfigReaderInterface
     */
    private $repositoryConfigReader;

    /**
     * @var RepositoryConfigWriterInterface
     */
    private $repositoryConfigWriter;

    /**
     * @var PackageConfigReaderInterface
     */
    private $packageConfigReader;

    /**
     * @var PackageConfigWriterInterface
     */
    private $packageConfigWriter;

    /**
     * @var RootPackageConfig
     */
    private $rootPackageConfig;

    /**
     * @var PackageRepositoryConfig
     */
    private $repositoryConfig;

    /**
     * @var PackageRepository
     */
    private $packageRepository;

    /**
     * Creates package manager with default configuration.
     *
     * @param string $rootDirectory The directory containing the root package.
     *
     * @return static The package manager.
     */
    public static function createDefault($rootDirectory)
    {
        $dispatcher = new EventDispatcher();

        return new static(
            $rootDirectory,
            $dispatcher,
            new RepositoryJsonReader(),
            new RepositoryJsonWriter(),
            new PackageJsonReader($dispatcher),
            new PackageJsonWriter($dispatcher)
        );
    }

    /**
     * Loads the repository at the given root directory.
     *
     * @param string                          $rootDirectory          The directory containing the root package.
     * @param EventDispatcherInterface        $dispatcher             The event dispatcher.
     * @param RepositoryConfigReaderInterface $repositoryConfigReader The repository config file reader.
     * @param RepositoryConfigWriterInterface $repositoryConfigWriter The repository config file writer.
     * @param PackageConfigReaderInterface    $packageConfigReader    The package config file reader.
     * @param PackageConfigWriterInterface    $packageConfigWriter    The package config file writer.
     */
    public function __construct(
        $rootDirectory,
        EventDispatcherInterface $dispatcher,
        RepositoryConfigReaderInterface $repositoryConfigReader,
        RepositoryConfigWriterInterface $repositoryConfigWriter,
        PackageConfigReaderInterface $packageConfigReader,
        PackageConfigWriterInterface $packageConfigWriter
    )
    {
        $this->packageRepository = new PackageRepository();
        $this->dispatcher = $dispatcher;
        $this->repositoryConfigReader = $repositoryConfigReader;
        $this->repositoryConfigWriter = $repositoryConfigWriter;
        $this->packageConfigReader = $packageConfigReader;
        $this->packageConfigWriter = $packageConfigWriter;
        $this->rootPackageConfig = $packageConfigReader->readRootPackageConfig($rootDirectory.'/'.self::PACKAGE_CONFIG);

        $this->activatePlugins();
        $this->loadPackages($rootDirectory);
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
        $rootDir = $this->packageRepository->getRootPackage()->getInstallPath();
        $repoPath = $repoPath ?: $this->rootPackageConfig->getGeneratedResourceRepository();
        $repoPath = Path::makeAbsolute($repoPath, $rootDir);
        $repoDir = Path::getDirectory($repoPath);
        $cachePath = $cachePath ?: $this->rootPackageConfig->getResourceRepositoryCache();
        $cachePath = Path::makeAbsolute($cachePath, $rootDir);
        $relCachePath = Path::makeRelative($cachePath, Path::getDirectory($repoPath));

        $builder->loadPackages($this->packageRepository);
        $builder->buildRepository($repo);

        if (is_dir($cachePath)) {
            $filesystem = new Filesystem();
            $filesystem->remove($cachePath);
        }

        PhpCacheRepository::dumpRepository($repo, $cachePath);

        if (!file_exists($repoDir)) {
            mkdir($repoDir, 0777, true);
        }

        file_put_contents($repoPath, <<<EOF
<?php

// resource-repository.php generated by the Puli PackageManager

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
     * @throws NameConflictException If another package has the same name as
     *                               the installed package.
     */
    public function installPackage($installPath)
    {
        $rootDirectory = $this->packageRepository->getRootPackage()->getInstallPath();
        $installPath = Path::makeAbsolute($installPath, $rootDirectory);

        if ($this->isPackageInstalled($installPath)) {
            return;
        }

        // Try to load the package
        $config = $this->packageConfigReader->readPackageConfig($installPath.'/'.self::PACKAGE_CONFIG);
        $packageName = $config->getPackageName();

        if ($this->packageRepository->containsPackage($packageName)) {
            $conflictingPackage = $this->packageRepository->getPackage($packageName);

            throw new NameConflictException(sprintf(
                'The package name "%s" is already in use by the package at '.
                '%s. Could not install package %s.',
                $packageName,
                $conflictingPackage->getInstallPath(),
                $installPath
            ));
        }

        // OK, now add it
        $package = new Package($config, $installPath);
        $relInstallPath = Path::makeRelative($installPath, $rootDirectory);
        $this->repositoryConfig->addPackageDescriptor(new PackageDescriptor($relInstallPath));
        $this->packageRepository->addPackage($package);

        // Write package repository configuration
        $configPath = $this->rootPackageConfig->getPackageRepositoryConfig();
        $configPath = Path::makeAbsolute($configPath, $rootDirectory);

        $this->repositoryConfigWriter->writeRepositoryConfig($this->repositoryConfig, $configPath);
    }

    /**
     * @return PackageRepository
     */
    public function getPackageRepository()
    {
        return $this->packageRepository;
    }

    /**
     * @return PackageRepositoryConfig
     */
    public function getRepositoryConfig()
    {
        return $this->repositoryConfig;
    }

    /**
     * @return RootPackageConfig
     */
    public function getRootPackageConfig()
    {
        return $this->rootPackageConfig;
    }

    private function activatePlugins()
    {
        foreach ($this->rootPackageConfig->getPluginClasses() as $pluginClass) {
            /** @var PluginInterface $plugin */
            $plugin = new $pluginClass();
            $plugin->activate($this, $this->dispatcher);
        }
    }

    private function loadPackages($rootDirectory)
    {
        $this->packageRepository->addPackage(new RootPackage($this->rootPackageConfig, $rootDirectory));

        $repositoryConfig = $this->rootPackageConfig->getPackageRepositoryConfig();
        $repositoryConfig = Path::makeAbsolute($repositoryConfig, $rootDirectory);

        $this->repositoryConfig = $this->repositoryConfigReader->readRepositoryConfig($repositoryConfig);

        foreach ($this->repositoryConfig->getPackageDescriptors() as $packageDefinition) {
            $installPath = Path::makeAbsolute($packageDefinition->getInstallPath(),
                $rootDirectory);
            $config = $this->packageConfigReader->readPackageConfig($installPath.'/'.self::PACKAGE_CONFIG);
            $packageName = $config->getPackageName();

            if ($this->packageRepository->containsPackage($packageName)) {
                $conflictingPackage = $this->packageRepository->getPackage($packageName);

                throw new NameConflictException(sprintf(
                    'Failed to load repository %s: The packages %s and %s have '.
                    'the same name "%s".',
                    $repositoryConfig,
                    $conflictingPackage->getInstallPath(),
                    $installPath,
                    $packageName
                ));
            }

            $this->packageRepository->addPackage(new Package($config, $installPath));
        }
    }

    private function isPackageInstalled($installPath)
    {
        foreach ($this->getPackageRepository()->getPackages() as $package) {
            if ($installPath === $package->getInstallPath()) {
                return true;
            }
        }

        return false;
    }
}
