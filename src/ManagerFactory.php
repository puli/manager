<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager;

use Psr\Log\LoggerInterface;
use Puli\Repository\ResourceRepository;
use Puli\Repository\ResourceRepositoryInterface;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFileManager;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFileStorage;
use Puli\RepositoryManager\Config\ConfigFile\Reader\ConfigJsonReader;
use Puli\RepositoryManager\Config\ConfigFile\Writer\ConfigJsonWriter;
use Puli\RepositoryManager\Discovery\DiscoveryManager;
use Puli\RepositoryManager\Environment\GlobalEnvironment;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\Reader\PackageJsonReader;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFileManager;
use Puli\RepositoryManager\Package\PackageFile\Writer\PackageJsonWriter;
use Puli\RepositoryManager\Package\PackageManager;
use Puli\RepositoryManager\Package\PackageState;
use Puli\RepositoryManager\Repository\RepositoryManager;
use Puli\RepositoryManager\Util\System;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Creates environment and manager instances.
 *
 * Use this class to bootstrap the managers provided by this package:
 *
 * ```php
 * $factory = new ManagerFactory();
 * $environment = $factory->createProjectEnvironment(getcwd());
 * $packageManager = $factory->createPackageManager($environment);
 * ```
 *
 * To create one of the managers, you first need to create the environment that
 * the manager will work with. There are two kinds of environments:
 *
 *  * The "global environment" is not tied to a specific root package. A global
 *    environment only loads the settings of the "config.json" file in the home
 *    directory. Global environments are created with
 *    {@link createGlobalEnvironment()}.
 *
 *  * The "project environment" is tied to a specific Puli project. You need to
 *    pass the path to the project to the factory. The local environment will
 *    then give access to both global settings set in "config.json" in the
 *    home directory and local settings stored in "puli.json" of the project.
 *    Local environments are created with {@link createProjectEnvironment()}.
 *
 * The factory creates four kinds of managers:
 *
 *  * The "config file manager" allows you to modify entries of the
 *    "config.json" file in the home directory. Config file managers are
 *    created with {@link createConfigFileManager()}.
 *
 *  * The "package file manager" manages modifications to the "puli.json" file
 *    of a Puli project. Use {@link createRootPackageFileManager()} to create it.
 *
 *  * The "package manager" manages the package repository of a Puli project.
 *    A package manager can be created with {@link createPackageManager()}.
 *
 *  * The "repository manager" manages the resource repository of a Puli
 *    project. It can be created with {@link createRepositoryManager()}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ManagerFactory
{
    /**
     * @var ConfigFileStorage|null
     */
    private $configFileStorage;

    /**
     * @var PackageFileStorage[]
     */
    private $packageFileStorages;

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
     * Creates the global environment.
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
     * @return GlobalEnvironment The global environment.
     */
    public function createGlobalEnvironment()
    {
        $dispatcher = new EventDispatcher();

        $homeDir = self::parseHomeDirectory();

        return new GlobalEnvironment(
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
     *
     * @throws FileNotFoundException If the path does not exist.
     * @throws NoDirectoryException If the path points to a file.
     */
    public function createProjectEnvironment($rootDir)
    {
        $dispatcher = new EventDispatcher();

        $homeDir = self::parseHomeDirectory();

        return new ProjectEnvironment(
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
    public function createConfigFileManager(GlobalEnvironment $environment)
    {
        return new ConfigFileManager(
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
    public function createRootPackageFileManager(ProjectEnvironment $environment)
    {
        return new RootPackageFileManager(
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
    public function createPackageManager(ProjectEnvironment $environment)
    {
        return new PackageManager(
            $environment,
            $this->getPackageFileStorage($environment->getEventDispatcher())
        );
    }

    /**
     * Creates a resource repository manager.
     *
     * @param ProjectEnvironment $environment    The project environment.
     * @param PackageManager     $packageManager The package manager. Optional.
     *
     * @return RepositoryManager The repository manager.
     */
    public function createRepositoryManager(ProjectEnvironment $environment, PackageManager $packageManager = null)
    {
        $packageManager = $packageManager ?: $this->createPackageManager($environment);

        return new RepositoryManager(
            $environment,
            $packageManager->getPackages(PackageState::ENABLED),
            $this->getPackageFileStorage($environment->getEventDispatcher())
        );
    }

    /**
     * Creates a resource discovery manager.
     *
     * @param ProjectEnvironment $environment    The project environment.
     * @param PackageManager     $packageManager The package manager. Optional.
     * @param LoggerInterface    $logger         The logger. Optional.
     *
     * @return DiscoveryManager The discovery manager.
     */
    public function createDiscoveryManager(ProjectEnvironment $environment, PackageManager $packageManager = null, LoggerInterface $logger = null)
    {
        $packageManager = $packageManager ?: $this->createPackageManager($environment);

        return new DiscoveryManager(
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
