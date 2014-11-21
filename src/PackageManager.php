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
use Puli\PackageManager\Config\Reader\ConfigJsonReader;
use Puli\PackageManager\Config\Writer\ConfigJsonWriter;
use Puli\PackageManager\Package\Config\Reader\PackageJsonReader;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\Config\Writer\PackageJsonWriter;
use Puli\PackageManager\Package\Package;
use Puli\PackageManager\Package\RootPackage;
use Puli\PackageManager\Plugin\PluginInterface;
use Puli\PackageManager\Repository\Config\PackageDescriptor;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;
use Puli\PackageManager\Repository\Config\Reader\RepositoryJsonReader;
use Puli\PackageManager\Repository\Config\Writer\RepositoryJsonWriter;
use Puli\PackageManager\Repository\NoSuchPackageException;
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
 * Many parts of this class are inspired by Composer.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageManager
{
    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var PuliEnvironment
     */
    private $environment;

    /**
     * @var ConfigManager
     */
    private $configManager;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

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
     * Creates a configuration manager with default dependencies.
     *
     * @param EventDispatcherInterface|null $dispatcher The event dispatcher to use. Optional.
     *
     * @return ConfigManager The configuration manager.
     */
    public static function createConfigManager(EventDispatcherInterface $dispatcher = null)
    {
        return new ConfigManager(
            new ConfigJsonReader(),
            new ConfigJsonWriter(),
            new RepositoryJsonReader(),
            new RepositoryJsonWriter(),
            new PackageJsonReader(),
            new PackageJsonWriter(),
            $dispatcher
        );
    }

    /**
     * Creates the Puli environment from the system's environment variables.
     *
     * A .htaccess file is put into Puli's home directory to protect it from
     * web access.
     *
     * @param ConfigManager|null $configManager The configuration file manager.
     *                                          If none is passed, the default
     *                                          configuration file manager is
     *                                          created.
     *
     * @return PuliEnvironment The created environment.
     *
     * @throws BootstrapException If the home directory is not found or is not
     *                            a directory.
     * @throws InvalidConfigException If the global configuration is invalid.
     *
     * @see PuliEnvironment::parseHomeDirectory()
     */
    public static function createEnvironment(ConfigManager $configManager = null)
    {
        $homeDir = PuliEnvironment::parseHomeDirectory();

        PuliEnvironment::denyWebAccess($homeDir);

        return new PuliEnvironment($homeDir, $configManager ?: self::createConfigManager());
    }

    /**
     * Creates package manager with default dependencies.
     *
     * @param string $rootDir The directory path to the root package.
     *
     * @return static The package manager.
     *
     * @throws BootstrapException If the home directory is not found or is not
     *                            a directory.
     * @throws InvalidConfigException If the global configuration or the
     *                                configuration of the root package is
     *                                invalid.
     */
    public static function createPackageManager($rootDir)
    {
        $dispatcher = new EventDispatcher();
        $configManager = self::createConfigManager($dispatcher);
        $environment = self::createEnvironment($configManager);

        return new static($rootDir, $environment, $configManager, $dispatcher);
    }

    /**
     * Loads the repository at the given root directory.
     *
     * @param string                   $rootDir       The directory containing the root package.
     * @param PuliEnvironment          $environment   The system environment.
     * @param ConfigManager            $configManager The config file manager.
     * @param EventDispatcherInterface $dispatcher    The event dispatcher.
     */
    public function __construct($rootDir, PuliEnvironment $environment, ConfigManager $configManager, EventDispatcherInterface $dispatcher)
    {
        $this->packageRepository = new PackageRepository();
        $this->rootDir = $rootDir;
        $this->environment = $environment;
        $this->configManager = $configManager;
        $this->dispatcher = $dispatcher;
        $this->rootPackageConfig = $configManager->loadRootPackageConfig($rootDir.'/puli.json', $environment->getGlobalConfig());

        $this->activatePlugins();
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

        $this->configManager->saveRepositoryConfig($this->repositoryConfig);
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
     * Installs a plugin class.
     *
     * The plugin class must be passed as fully-qualified name of a class that
     * implements {@link \Puli\PackageManager\Plugin\PluginInterface}. Plugin
     * constructors must not have mandatory parameters.
     *
     * By default, plugins are installed in the configuration of the root
     * package. Set the parameter `$global` to `true` if you want to install the
     * plugin globally.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     * @param bool   $global      Whether to install the plugin system-wide.
     *                            Defaults to `false?.
     *
     * @throws InvalidConfigException If a class is not found, is not a class,
     *                                does not implement
     *                                {@link \Puli\PackageManager\Plugin\PluginInterface}
     *                                or has required constructor parameters.
     */
    public function installPluginClass($pluginClass, $global = false)
    {
        if ($this->environment->isGlobalPluginClassInstalled($pluginClass)) {
            // Already installed globally
            return;
        }

        if ($global) {
            $this->environment->installGlobalPluginClass($pluginClass);

            return;
        }

        if ($this->rootPackageConfig->hasPluginClass($pluginClass)) {
            // Already installed locally
            return;
        }

        $this->rootPackageConfig->addPluginClass($pluginClass);

        $this->configManager->savePackageConfig($this->rootPackageConfig);
    }

    /**
     * Returns whether a plugin class is installed.
     *
     * @param string $pluginClass   The fully qualified plugin class name.
     * @param bool   $includeGlobal If set to `true`, both plugins installed in
     *                              the configuration of the root package and
     *                              plugins installed in the global configuration
     *                              are considered. If set to `false`, only the
     *                              plugins defined in the root package are
     *                              considered.
     *
     * @return bool Whether the plugin class is installed.
     *
     * @see installPluginClass()
     */
    public function isPluginClassInstalled($pluginClass, $includeGlobal = true)
    {
        return $this->rootPackageConfig->hasPluginClass($pluginClass, $includeGlobal);
    }

    /**
     * Returns all installed plugin classes.
     *
     * @param bool $includeGlobal If set to `true`, both plugins installed in
     *                            the configuration of the root package and
     *                            plugins installed in the global configuration
     *                            are returned. If set to `false`, only the
     *                            plugins defined in the root package are
     *                            returned.
     *
     * @return string[] The fully qualified plugin class names.
     *
     * @see installPluginClass()
     */
    public function getPluginClasses($includeGlobal = true)
    {
        return $this->rootPackageConfig->getPluginClasses($includeGlobal);
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

    /**
     * Loads all packages referenced by the package repository configuration.
     *
     * @throws InvalidConfigException If a package is not configured correctly.
     * @throws NameConflictException If a package has the same name as another
     *                               loaded package.
     */
    private function loadPackages()
    {
        $this->packageRepository->addPackage(new RootPackage($this->rootPackageConfig, $this->rootDir));

        $configPath = $this->rootPackageConfig->getPackageRepositoryConfig();
        $configPath = Path::makeAbsolute($configPath, $this->rootDir);

        $this->repositoryConfig = $this->configManager->loadRepositoryConfig($configPath);

        foreach ($this->repositoryConfig->getPackageDescriptors() as $packageDefinition) {
            $installPath = Path::makeAbsolute($packageDefinition->getInstallPath(), $this->rootDir);
            $package = $this->loadPackage($installPath);

            $this->packageRepository->addPackage($package);
        }
    }

    private function loadPackage($installPath)
    {
        $config = $this->configManager->loadPackageConfig($installPath.'/puli.json');
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
