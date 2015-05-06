<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api;

use LogicException;
use Psr\Log\LoggerInterface;
use Puli\Discovery\Api\EditableDiscovery;
use Puli\Discovery\Api\ResourceDiscovery;
use Puli\Factory\PuliFactory;
use Puli\Manager\Api\Asset\AssetManager;
use Puli\Manager\Api\Config\ConfigFileManager;
use Puli\Manager\Api\Config\ConfigFileReader;
use Puli\Manager\Api\Config\ConfigFileWriter;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Environment\GlobalEnvironment;
use Puli\Manager\Api\Environment\ProjectEnvironment;
use Puli\Manager\Api\Factory\FactoryManager;
use Puli\Manager\Api\Installation\InstallationManager;
use Puli\Manager\Api\Installer\InstallerManager;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageFileReader;
use Puli\Manager\Api\Package\PackageFileWriter;
use Puli\Manager\Api\Package\PackageManager;
use Puli\Manager\Api\Package\PackageState;
use Puli\Manager\Api\Package\RootPackageFileManager;
use Puli\Manager\Api\Repository\RepositoryManager;
use Puli\Manager\Api\Server\ServerManager;
use Puli\Manager\Assert\Assert;
use Puli\Manager\Asset\DiscoveryAssetManager;
use Puli\Manager\Config\ConfigFileManagerImpl;
use Puli\Manager\Config\ConfigFileStorage;
use Puli\Manager\Config\ConfigJsonReader;
use Puli\Manager\Config\ConfigJsonWriter;
use Puli\Manager\Config\DefaultConfig;
use Puli\Manager\Config\EnvConfig;
use Puli\Manager\Discovery\DiscoveryManagerImpl;
use Puli\Manager\Factory\FactoryManagerImpl;
use Puli\Manager\Factory\Generator\DefaultGeneratorRegistry;
use Puli\Manager\Installation\InstallationManagerImpl;
use Puli\Manager\Installer\PackageFileInstallerManager;
use Puli\Manager\Package\PackageFileStorage;
use Puli\Manager\Package\PackageJsonReader;
use Puli\Manager\Package\PackageJsonWriter;
use Puli\Manager\Package\PackageManagerImpl;
use Puli\Manager\Package\RootPackageFileManagerImpl;
use Puli\Manager\Php\ClassWriter;
use Puli\Manager\Repository\RepositoryManagerImpl;
use Puli\Manager\Server\PackageFileServerManager;
use Puli\Manager\Util\System;
use Puli\Repository\Api\EditableRepository;
use Puli\Repository\Api\ResourceRepository;
use Puli\UrlGenerator\Api\UrlGenerator;
use Puli\UrlGenerator\DiscoveryUrlGenerator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webmozart\Expression\Expr;
use Webmozart\PathUtil\Path;

/**
 * The Puli service locator.
 *
 * Use this class to access the managers provided by this package:
 *
 * ```php
 * $puli = new Puli(getcwd());
 * $puli->start();
 *
 * $packageManager = $puli->getPackageManager();
 * ```
 *
 * The `Puli` class either operates in the global or a project environment:
 *
 *  * The "global environment" is not tied to a specific root package. A global
 *    environment only loads the settings of the "config.json" file in the home
 *    directory. The `Puli` class operates in the global environment if no
 *    project root directory is passed to the constructor. In the global
 *    environment, only the global config file manager is available.
 *  * The "project environment" is tied to a specific Puli project. You need to
 *    pass the path to the project's root directory to the constructor or to
 *    {@link setRootDirectory()}. The configuration of the "puli.json" file in
 *    the root directory is used to configure the managers.
 *
 * The `Puli` class creates four kinds of managers:
 *
 *  * The "config file manager" allows you to modify entries of the
 *    "config.json" file in the home directory.
 *  * The "package file manager" manages modifications to the "puli.json" file
 *    of a Puli project.
 *  * The "package manager" manages the package repository of a Puli project.
 *  * The "repository manager" manages the resource repository of a Puli
 *    project.
 *  * The "discovery manager" manages the resource discovery of a Puli project.
 *
 * The home directory is read from the environment variable "PULI_HOME".
 * If this variable is not set, the home directory defaults to:
 *
 *  * `$HOME/.puli` on Linux, where `$HOME` is the environment variable
 *    "HOME".
 *  * `$APPDATA/Puli` on Windows, where `$APPDATA` is the environment
 *    variable "APPDATA".
 *
 * If none of these variables can be found, an exception is thrown.
 *
 * A .htaccess file is put into the home directory to protect it from web
 * access.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Puli
{
    /**
     * @var string|null
     */
    private $rootDir;

    /**
     * @var EventDispatcherInterface|null
     */
    private $dispatcher;

    /**
     * @var GlobalEnvironment|ProjectEnvironment
     */
    private $environment;

    /**
     * @var ResourceRepository
     */
    private $repo;

    /**
     * @var ResourceDiscovery
     */
    private $discovery;

    /**
     * @var PuliFactory
     */
    private $factory;

    /**
     * @var FactoryManager
     */
    private $factoryManager;

    /**
     * @var ConfigFileManager
     */
    private $configFileManager;

    /**
     * @var RootPackageFileManager
     */
    private $rootPackageFileManager;

    /**
     * @var PackageManager
     */
    private $packageManager;

    /**
     * @var RepositoryManager
     */
    private $repositoryManager;

    /**
     * @var DiscoveryManager
     */
    private $discoveryManager;

    /**
     * @var AssetManager
     */
    private $assetManager;

    /**
     * @var InstallationManager
     */
    private $installationManager;

    /**
     * @var InstallerManager
     */
    private $installerManager;

    /**
     * @var ServerManager
     */
    private $serverManager;

    /**
     * @var UrlGenerator
     */
    private $urlGenerator;

    /**
     * @var ConfigFileStorage|null
     */
    private $configFileStorage;

    /**
     * @var ConfigFileReader|null
     */
    private $configFileReader;

    /**
     * @var ConfigFileWriter|null
     */
    private $configFileWriter;

    /**
     * @var PackageFileStorage|null
     */
    private $packageFileStorage;

    /**
     * @var PackageFileReader|null
     */
    private $packageFileReader;

    /**
     * @var PackageFileWriter|null
     */
    private $packageFileWriter;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    private $started = false;

    /**
     * @var bool
     */
    private $pluginsEnabled = true;

    /**
     * Parses the system environment for a home directory.
     *
     * @return null|string Returns the path to the home directory or `null`
     *                     if none was found.
     */
    private static function parseHomeDirectory()
    {
        try {
            $homeDir = System::parseHomeDirectory();

            System::denyWebAccess($homeDir);

            return $homeDir;
        } catch (InvalidConfigException $e) {
            // Environment variable was not found -> no home directory
            // This happens often on web servers where the home directory is
            // not set manually
            return null;
        }
    }

    /**
     * Creates a new instance for the given Puli project.
     *
     * @param string $rootDir The root directory of the Puli project. If none is
     *                        passed, the object operates in the global
     *                        environment. You can set or switch the root
     *                        directories later on by calling
     *                        {@link setRootDirectory()}.
     *
     * @see Puli, start()
     */
    public function __construct($rootDir = null)
    {
        $this->setRootDirectory($rootDir);
    }

    /**
     * Starts the service container.
     */
    public function start()
    {
        if ($this->started) {
            throw new LogicException('Puli is already started');
        }

        if ($this->rootDir) {
            $this->environment = $this->createProjectEnvironment($this->rootDir);
        } else {
            $this->environment = $this->createGlobalEnvironment();
        }

        $this->dispatcher = $this->environment->getEventDispatcher();
        $this->started = true;

        // Start plugins once the container is running
        if ($this->rootDir && $this->pluginsEnabled) {
            $this->activatePlugins();
        }
    }

    /**
     * Returns whether the service container is started.
     *
     * @return bool Returns `true` if the container is started and `false`
     *              otherwise.
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * Sets the root directory of the managed Puli project.
     *
     * @param string|null $rootDir The root directory of the managed Puli
     *                             project or `null` to start Puli in the
     *                             global environment.
     */
    public function setRootDirectory($rootDir)
    {
        if ($this->started) {
            throw new LogicException('Puli is already started');
        }

        Assert::nullOrDirectory($rootDir);

        $this->rootDir = $rootDir;
    }

    /**
     * Returns the root directory of the managed Puli project.
     *
     * If no Puli project is managed at the moment, `null` is returned.
     *
     * @return string|null The root directory of the managed Puli project or
     *                     `null` if none is set.
     */
    public function getRootDirectory()
    {
        return $this->rootDir;
    }

    /**
     * Sets the logger to use.
     *
     * @param LoggerInterface $logger The logger to use.
     */
    public function setLogger(LoggerInterface $logger)
    {
        if ($this->started) {
            throw new LogicException('Puli is already started');
        }

        $this->logger = $logger;
    }

    /**
     * Returns the used logger.
     *
     * @return LoggerInterface The used logger.
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Sets the event dispatcher to use.
     *
     * @param EventDispatcherInterface $dispatcher The event dispatcher to use.
     */
    public function setEventDispatcher(EventDispatcherInterface $dispatcher)
    {
        if ($this->started) {
            throw new LogicException('Puli is already started');
        }

        $this->dispatcher = $dispatcher;
    }

    /**
     * Returns the used event dispatcher.
     *
     * @return EventDispatcherInterface The used logger.
     */
    public function getEventDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Enables all Puli plugins.
     */
    public function enablePlugins()
    {
        $this->pluginsEnabled = true;
    }

    /**
     * Disables all Puli plugins.
     */
    public function disablePlugins()
    {
        $this->pluginsEnabled = false;
    }

    /**
     * Returns whether Puli plugins are enabled.
     *
     * @return bool Returns `true` if Puli plugins will be loaded and `false`
     *              otherwise.
     */
    public function arePluginsEnabled()
    {
        return $this->pluginsEnabled;
    }

    /**
     * Returns the environment.
     *
     * @return GlobalEnvironment|ProjectEnvironment The environment.
     */
    public function getEnvironment()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        return $this->environment;
    }

    /**
     * Returns the resource repository of the project.
     *
     * @return EditableRepository The resource repository.
     */
    public function getRepository()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->rootDir) {
            return null;
        }

        if (!$this->repo) {
            $this->repo = $this->getFactory()->createRepository();
        }

        return $this->repo;
    }

    /**
     * Returns the resource discovery of the project.
     *
     * @return EditableDiscovery The resource discovery.
     */
    public function getDiscovery()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->rootDir) {
            return null;
        }

        if (!$this->discovery) {
            $this->discovery = $this->getFactory()->createDiscovery($this->getRepository());
        }

        return $this->discovery;
    }

    /**
     * @return PuliFactory
     */
    public function getFactory()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->factory && $this->rootDir) {
            $this->factory = $this->getFactoryManager()->createFactory();
        }

        return $this->factory;
    }

    /**
     * @return FactoryManager
     */
    public function getFactoryManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->factoryManager && $this->rootDir) {
            $this->factoryManager = new FactoryManagerImpl(
                $this->environment,
                new DefaultGeneratorRegistry(),
                new ClassWriter()
            );

            // Don't set via the constructor to prevent a cyclic dependency
            $this->factoryManager->setServers($this->getServerManager()->getServers());
        }

        return $this->factoryManager;
    }

    /**
     * Returns the configuration file manager.
     *
     * @return ConfigFileManager The configuration file manager.
     */
    public function getConfigFileManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->configFileManager && $this->environment->getHomeDirectory()) {
            $this->configFileManager = new ConfigFileManagerImpl(
                $this->environment,
                $this->getConfigFileStorage(),
                $this->getFactoryManager()
            );
        }

        return $this->configFileManager;
    }

    /**
     * Returns the root package file manager.
     *
     * @return RootPackageFileManager The package file manager.
     */
    public function getRootPackageFileManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->rootPackageFileManager && $this->rootDir) {
            $this->rootPackageFileManager = new RootPackageFileManagerImpl(
                $this->environment,
                $this->getPackageFileStorage()
            );
        }

        return $this->rootPackageFileManager;
    }

    /**
     * Returns the package manager.
     *
     * @return PackageManager The package manager.
     */
    public function getPackageManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->packageManager && $this->rootDir) {
            $this->packageManager = new PackageManagerImpl(
                $this->environment,
                $this->getPackageFileStorage()
            );
        }

        return $this->packageManager;
    }

    /**
     * Returns the resource repository manager.
     *
     * @return RepositoryManager The repository manager.
     */
    public function getRepositoryManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->repositoryManager && $this->rootDir) {
            $this->repositoryManager = new RepositoryManagerImpl(
                $this->environment,
                $this->getRepository(),
                $this->getPackageManager()->findPackages(Expr::same(PackageState::ENABLED, Package::STATE)),
                $this->getPackageFileStorage()
            );
        }

        return $this->repositoryManager;
    }

    /**
     * Returns the resource discovery manager.
     *
     * @return DiscoveryManager The discovery manager.
     */
    public function getDiscoveryManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->discoveryManager && $this->rootDir) {
            $this->discoveryManager = new DiscoveryManagerImpl(
                $this->environment,
                $this->getDiscovery(),
                $this->getPackageManager()->findPackages(Expr::same(PackageState::ENABLED, Package::STATE)),
                $this->getPackageFileStorage(),
                $this->logger
            );
        }

        return $this->discoveryManager;
    }

    /**
     * Returns the asset manager.
     *
     * @return AssetManager The asset manager.
     */
    public function getAssetManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->assetManager && $this->rootDir) {
            $this->assetManager = new DiscoveryAssetManager(
                $this->getDiscoveryManager(),
                $this->getServerManager()->getServers()
            );
        }

        return $this->assetManager;
    }

    /**
     * Returns the installation manager.
     *
     * @return InstallationManager The installation manager.
     */
    public function getInstallationManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->installationManager && $this->rootDir) {
            $this->installationManager = new InstallationManagerImpl(
                $this->getEnvironment(),
                $this->getRepository(),
                $this->getServerManager()->getServers(),
                $this->getInstallerManager()
            );
        }

        return $this->installationManager;
    }

    /**
     * Returns the installer manager.
     *
     * @return InstallerManager The installer manager.
     */
    public function getInstallerManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->installerManager && $this->rootDir) {
            $this->installerManager = new PackageFileInstallerManager(
                $this->getRootPackageFileManager(),
                $this->getPackageManager()->getPackages()
            );
        }

        return $this->installerManager;
    }

    /**
     * Returns the server manager.
     *
     * @return ServerManager The server manager.
     */
    public function getServerManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->serverManager && $this->rootDir) {
            $this->serverManager = new PackageFileServerManager(
                $this->getRootPackageFileManager(),
                $this->getInstallerManager()
            );
        }

        return $this->serverManager;
    }

    /**
     * Returns the resource URL generator.
     *
     * @return UrlGenerator The resource URL generator.
     */
    public function getUrlGenerator()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->urlGenerator && $this->rootDir) {
            $urlFormats = array();
            foreach ($this->getServerManager()->getServers() as $server) {
                $urlFormats[$server->getName()] = $server->getUrlFormat();
            }

            $this->urlGenerator = new DiscoveryUrlGenerator($this->getDiscovery(), $urlFormats);
        }

        return $this->urlGenerator;
    }

    private function activatePlugins()
    {
        foreach ($this->environment->getRootPackageFile()->getPluginClasses() as $pluginClass) {
            $this->validatePluginClass($pluginClass);

            /** @var PuliPlugin $plugin */
            $plugin = new $pluginClass();
            $plugin->activate($this);
        }
    }

    private function createGlobalEnvironment()
    {
        $homeDir = self::parseHomeDirectory();

        if (null !== $homeDir) {
            Assert::fileExists($homeDir, 'Could not load Puli environment: The home directory %s does not exist.');
            Assert::directory($homeDir, 'Could not load Puli environment: The home directory %s is a file. Expected a directory.');

            // Create a storage without the factory manager
            $configStorage = new ConfigFileStorage($this->getConfigFileReader(), $this->getConfigFileWriter());
            $configPath = Path::canonicalize($homeDir).'/config.json';
            $configFile = $configStorage->loadConfigFile($configPath, new DefaultConfig());
            $baseConfig = $configFile->getConfig();
        } else {
            $configFile = null;
            $baseConfig = new DefaultConfig();
        }

        $config = new EnvConfig($baseConfig);

        return new GlobalEnvironment($homeDir, $config, $configFile, $this->dispatcher);
    }

    /**
     * Creates the environment of a Puli project.
     *
     * The home directory is read from the environment variable "PULI_HOME".
     * If this variable is not set, the home directory defaults to:
     *
     *  * `$HOME/.puli` on Linux, where `$HOME` is the environment variable
     *    "HOME".
     *  * `$APPDATA/Puli` on Windows, where `$APPDATA` is the environment
     *    variable "APPDATA".
     *
     * If none of these variables can be found, an exception is thrown.
     *
     * A .htaccess file is put into the home directory to protect it from web
     * access.
     *
     * @param string $rootDir The path to the project.
     *
     * @return ProjectEnvironment The project environment.
     */
    private function createProjectEnvironment($rootDir)
    {
        Assert::fileExists($rootDir, 'Could not load Puli environment: The root %s does not exist.');
        Assert::directory($rootDir, 'Could not load Puli environment: The root %s is a file. Expected a directory.');

        $homeDir = self::parseHomeDirectory();

        if (null !== $homeDir) {
            Assert::fileExists($homeDir, 'Could not load Puli environment: The home directory %s does not exist.');
            Assert::directory($homeDir, 'Could not load Puli environment: The home directory %s is a file. Expected a directory.');

            // Create a storage without the factory manager
            $configStorage = new ConfigFileStorage($this->getConfigFileReader(), $this->getConfigFileWriter());
            $configPath = Path::canonicalize($homeDir).'/config.json';
            $configFile = $configStorage->loadConfigFile($configPath, new DefaultConfig());
            $baseConfig = $configFile->getConfig();
        } else {
            $configFile = null;
            $baseConfig = new DefaultConfig();
        }

        // Create a storage without the factory manager
        $packageFileStorage = new PackageFileStorage($this->getPackageFileReader(), $this->getPackageFileWriter());
        $rootDir = Path::canonicalize($rootDir);
        $rootFilePath = $this->rootDir.'/puli.json';
        $rootPackageFile = $packageFileStorage->loadRootPackageFile($rootFilePath, $baseConfig);
        $config = new EnvConfig($rootPackageFile->getConfig());

        return new ProjectEnvironment($homeDir, $rootDir, $config, $rootPackageFile, $configFile, $this->dispatcher);
    }

    /**
     * Returns the cached configuration file storage.
     *
     * @return ConfigFileStorage The configuration file storage.
     */
    private function getConfigFileStorage()
    {
        if (!$this->configFileStorage) {
            $this->configFileStorage = new ConfigFileStorage(
                $this->getConfigFileReader(),
                $this->getConfigFileWriter(),
                $this->getFactoryManager()
            );
        }

        return $this->configFileStorage;
    }

    /**
     * Returns the cached configuration file reader.
     *
     * @return ConfigFileReader The configuration file reader.
     */
    private function getConfigFileReader()
    {
        if (!$this->configFileReader) {
            $this->configFileReader = new ConfigJsonReader();
        }

        return $this->configFileReader;
    }

    /**
     * Returns the cached configuration file writer.
     *
     * @return ConfigFileWriter The configuration file writer.
     */
    private function getConfigFileWriter()
    {
        if (!$this->configFileWriter) {
            $this->configFileWriter = new ConfigJsonWriter();
        }

        return $this->configFileWriter;
    }

    /**
     * Returns the cached package file storage.
     *
     * @return PackageFileStorage The package file storage.
     */
    private function getPackageFileStorage()
    {
        if (!$this->packageFileStorage) {
            $this->packageFileStorage = new PackageFileStorage(
                $this->getPackageFileReader(),
                $this->getPackageFileWriter(),
                $this->getFactoryManager()
            );
        }

        return $this->packageFileStorage;
    }

    /**
     * Returns the cached package file reader.
     *
     * @return PackageFileReader The package file reader.
     */
    private function getPackageFileReader()
    {
        if (!$this->packageFileReader) {
            $this->packageFileReader = new PackageJsonReader();
        }

        return $this->packageFileReader;
    }

    /**
     * Returns the cached package file writer.
     *
     * @return PackageFileWriter The package file writer.
     */
    private function getPackageFileWriter()
    {
        if (!$this->packageFileWriter) {
            $this->packageFileWriter = new PackageJsonWriter();
        }

        return $this->packageFileWriter;
    }

    /**
     * Validates the given plugin class name.
     *
     * @param string $pluginClass The fully qualified name of a plugin class.
     */
    private function validatePluginClass($pluginClass)
    {
        if (!class_exists($pluginClass)) {
            throw new InvalidConfigException(sprintf(
                'The plugin class %s does not exist.',
                $pluginClass
            ));
        }

        if (!in_array('Puli\Manager\Api\PuliPlugin', class_implements($pluginClass))) {
            throw new InvalidConfigException(sprintf(
                'The plugin class %s must implement PuliPlugin.',
                $pluginClass
            ));
        }
    }
}
