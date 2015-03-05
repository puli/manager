<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api;

use LogicException;
use Psr\Log\LoggerInterface;
use Puli\Discovery\Api\EditableDiscovery;
use Puli\Repository\Api\EditableRepository;
use Puli\RepositoryManager\Api\Config\ConfigFileManager;
use Puli\RepositoryManager\Api\Discovery\DiscoveryManager;
use Puli\RepositoryManager\Api\Environment\GlobalEnvironment;
use Puli\RepositoryManager\Api\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Api\Package\PackageManager;
use Puli\RepositoryManager\Api\Package\PackageState;
use Puli\RepositoryManager\Api\Package\RootPackageFileManager;
use Puli\RepositoryManager\Api\Repository\RepositoryManager;
use Puli\RepositoryManager\Assert\Assert;
use Puli\RepositoryManager\Config\ConfigFileManagerImpl;
use Puli\RepositoryManager\Config\ConfigFileStorage;
use Puli\RepositoryManager\Config\ConfigJsonReader;
use Puli\RepositoryManager\Config\ConfigJsonWriter;
use Puli\RepositoryManager\Discovery\DiscoveryManagerImpl;
use Puli\RepositoryManager\Environment\GlobalEnvironmentImpl;
use Puli\RepositoryManager\Environment\ProjectEnvironmentImpl;
use Puli\RepositoryManager\Package\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageJsonReader;
use Puli\RepositoryManager\Package\PackageJsonWriter;
use Puli\RepositoryManager\Package\PackageManagerImpl;
use Puli\RepositoryManager\Package\RootPackageFileManagerImpl;
use Puli\RepositoryManager\Repository\RepositoryManagerImpl;
use Puli\RepositoryManager\Util\System;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
     * @var GlobalEnvironment|ProjectEnvironment
     */
    private $environment;

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
     * @var ConfigFileStorage|null
     */
    private $configFileStorage;

    /**
     * @var PackageFileStorage[]
     */
    private $packageFileStorages;

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
    private $initialized = false;

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

            if ($this->pluginsEnabled) {
                $this->activatePlugins();
            }
        } else {
            $this->environment = $this->createGlobalEnvironment();
        }

        $this->configFileManager = null;
        $this->rootPackageFileManager = null;
        $this->packageManager = null;
        $this->repositoryManager = null;
        $this->discoveryManager = null;
        $this->started = true;
        $this->initialized = false;
    }

    /**
     * Returns the root directory of the managed Puli project.
     *
     * If no Puli project is managed at the moment, `null` is returned.
     *
     * @param string|null $rootDir The root directory of the managed Puli
     *                             project or `null` to start Puli in the
     *                             global environment.
     */
    public function setRootDirectory($rootDir)
    {
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
     * All managers are reloaded after calling this method.
     *
     * @param LoggerInterface $logger The logger to use.
     */
    public function setLogger(LoggerInterface $logger)
    {
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

        if (!$this->environment instanceof ProjectEnvironment) {
            throw new LogicException('Cannot access the repository in the global environment.');
        }

        return $this->environment->getRepository();
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

        if (!$this->environment instanceof ProjectEnvironment) {
            throw new LogicException('Cannot access the discovery in the global environment.');
        }

        return $this->environment->getDiscovery();
    }

    /**
     * Returns the event dispatcher.
     *
     * @return EventDispatcherInterface The event dispatcher.
     */
    public function getEventDispatcher()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        return $this->environment->getEventDispatcher();
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

        if (!$this->initialized) {
            $this->initManagers();
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

        if (!$this->initialized) {
            $this->initManagers();
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

        if (!$this->initialized) {
            $this->initManagers();
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

        if (!$this->initialized) {
            $this->initManagers();
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

        if (!$this->initialized) {
            $this->initManagers();
        }

        return $this->discoveryManager;
    }

    private function activatePlugins()
    {
        foreach ($this->environment->getRootPackageFile()->getPluginClasses() as $pluginClass) {
            /** @var \Puli\RepositoryManager\Api\PuliPlugin $plugin */
            $plugin = new $pluginClass();
            $plugin->activate($this);
        }
    }

    private function initManagers()
    {
        if ($this->rootDir) {
            $this->initProjectManagers();
        } else {
            $this->initGlobalManagers();
        }

        $this->initialized = true;
    }

    private function initGlobalManagers()
    {
        $this->configFileManager = $this->environment->getHomeDirectory()
            ? $this->createConfigFileManager($this->environment)
            : null;
        $this->rootPackageFileManager = null;
        $this->packageManager = null;
        $this->repositoryManager = null;
        $this->discoveryManager = null;
    }

    private function initProjectManagers()
    {
        // Create all managers and bind them to the event dispatcher
        $this->configFileManager = $this->environment->getHomeDirectory()
            ? $this->createConfigFileManager($this->environment)
            : null;
        $this->rootPackageFileManager = $this->createRootPackageFileManager($this->environment);
        $this->packageManager = $this->createPackageManager($this->environment);
        $this->repositoryManager = $this->createRepositoryManager($this->environment, $this->packageManager);
        $this->discoveryManager = $this->createDiscoveryManager($this->environment, $this->packageManager, $this->logger);
    }

    private function createGlobalEnvironment()
    {
        $dispatcher = new EventDispatcher();

        $homeDir = self::parseHomeDirectory();

        return new GlobalEnvironmentImpl(
            $homeDir,
            $this->getConfigFileStorage(),
            $dispatcher
        );
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
        $dispatcher = new EventDispatcher();

        $homeDir = self::parseHomeDirectory();

        return new ProjectEnvironmentImpl(
            $homeDir,
            $rootDir,
            $this->getConfigFileStorage(),
            $this->getPackageFileStorage($dispatcher),
            $dispatcher
        );
    }

    /**
     * Creates a configuration file manager.
     *
     * @param GlobalEnvironment $environment The global environment.
     *
     * @return ConfigFileManager The created configuration file manager.
     */
    private function createConfigFileManager(GlobalEnvironment $environment)
    {
        return new ConfigFileManagerImpl(
            $environment,
            $this->getConfigFileStorage()
        );
    }

    /**
     * Creates a package file manager.
     *
     * @param ProjectEnvironment $environment The project environment.
     *
     * @return RootPackageFileManager The created package file manager.
     */
    private function createRootPackageFileManager(ProjectEnvironment $environment)
    {
        return new RootPackageFileManagerImpl(
            $environment,
            $this->getPackageFileStorage($environment->getEventDispatcher()),
            $this->createConfigFileManager($environment)
        );
    }

    /**
     * Creates a package manager.
     *
     * @param ProjectEnvironment $environment The project environment.
     *
     * @return PackageManager The package manager.
     */
    private function createPackageManager(ProjectEnvironment $environment)
    {
        return new PackageManagerImpl(
            $environment,
            $this->getPackageFileStorage($environment->getEventDispatcher())
        );
    }

    /**
     * Creates a resource repository manager.
     *
     * @param ProjectEnvironment $environment    The project environment.
     * @param PackageManager     $packageManager The package manager.
     *
     * @return RepositoryManager The repository manager.
     */
    private function createRepositoryManager(ProjectEnvironment $environment, PackageManager $packageManager)
    {
        return new RepositoryManagerImpl(
            $environment,
            $packageManager->getPackages(PackageState::ENABLED),
            $this->getPackageFileStorage($environment->getEventDispatcher())
        );
    }

    /**
     * Creates a resource discovery manager.
     *
     * @param ProjectEnvironment $environment    The project environment.
     * @param PackageManager     $packageManager The package manager.
     * @param LoggerInterface    $logger         The logger.
     *
     * @return DiscoveryManager The discovery manager.
     */
    private function createDiscoveryManager(ProjectEnvironment $environment, PackageManager $packageManager, LoggerInterface $logger = null)
    {
        return new DiscoveryManagerImpl(
            $environment,
            $packageManager->getPackages(PackageState::ENABLED),
            $this->getPackageFileStorage($environment->getEventDispatcher()),
            $logger
        );
    }

    /**
     * Returns the cached configuration file storage.
     *
     * @return ConfigFileStorage The file storage.
     */
    private function getConfigFileStorage()
    {
        if (!$this->configFileStorage) {
            $this->configFileStorage = new ConfigFileStorage(
                new ConfigJsonReader(),
                new ConfigJsonWriter()
            );
        }

        return $this->configFileStorage;
    }

    /**
     * Returns the cached package file storage.
     *
     * @param EventDispatcherInterface $dispatcher The event dispatcher that
     *                                             receives the package file
     *                                             storage events.
     *
     * @return PackageFileStorage The file storage.
     */
    private function getPackageFileStorage(EventDispatcherInterface $dispatcher)
    {
        $hash = spl_object_hash($dispatcher);

        if (!isset($this->packageFileStorages[$hash])) {
            $this->packageFileStorages[$hash] = new PackageFileStorage(
                new PackageJsonReader(),
                new PackageJsonWriter(),
                $dispatcher
            );
        }

        return $this->packageFileStorages[$hash];
    }
}
