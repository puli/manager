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
use Puli\PackageManager\Package\Package;
use Puli\PackageManager\Package\RootPackage;
use Puli\PackageManager\Plugin\PluginInterface;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;
use Puli\PackageManager\Repository\Config\Reader\RepositoryConfigReaderInterface;
use Puli\PackageManager\Repository\Config\Reader\RepositoryJsonReader;
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
     * @var EventDispatcher
     */
    private $dispatcher;

    /**
     * @var RepositoryConfigReaderInterface
     */
    private $repositoryConfigReader;

    /**
     * @var PackageConfigReaderInterface
     */
    private $packageConfigReader;

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
        return new static($rootDirectory, new EventDispatcher(), new RepositoryJsonReader(), new PackageJsonReader());
    }

    /**
     * Loads the repository at the given root directory.
     *
     * @param string                          $rootDirectory          The directory containing the root package.
     * @param EventDispatcherInterface        $dispatcher             The event dispatcher.
     * @param RepositoryConfigReaderInterface $repositoryConfigReader The repository config file reader.
     * @param PackageConfigReaderInterface    $packageConfigReader    The package config file reader.
     */
    public function __construct($rootDirectory, EventDispatcherInterface $dispatcher, RepositoryConfigReaderInterface $repositoryConfigReader, PackageConfigReaderInterface $packageConfigReader)
    {
        $this->packageRepository = new PackageRepository();
        $this->dispatcher = $dispatcher;
        $this->repositoryConfigReader = $repositoryConfigReader;
        $this->packageConfigReader = $packageConfigReader;
        $this->rootPackageConfig = $packageConfigReader->readRootPackageConfig($rootDirectory.'/puli.json');

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
        $this->repositoryConfig = $this->repositoryConfigReader->readRepositoryConfig($rootDirectory.'/'.$repositoryConfig);

        foreach ($this->repositoryConfig->getPackageDescriptors() as $packageDefinition) {
            $installPath = Path::makeAbsolute($packageDefinition->getInstallPath(),
                $rootDirectory);
            $config = $this->packageConfigReader->readPackageConfig($installPath.'/puli.json');
            $package = new Package($config, $installPath);

            $this->packageRepository->addPackage($package);
        }
    }
}
