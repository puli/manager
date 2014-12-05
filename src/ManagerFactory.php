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

use Puli\Repository\ResourceRepositoryInterface;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFileManager;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFileStorage;
use Puli\RepositoryManager\Config\ConfigFile\Reader\ConfigJsonReader;
use Puli\RepositoryManager\Config\ConfigFile\Writer\ConfigJsonWriter;
use Puli\RepositoryManager\Environment\GlobalEnvironment;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Package\PackageFile\PackageFileManager;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\Reader\PackageJsonReader;
use Puli\RepositoryManager\Package\PackageFile\Writer\PackageJsonWriter;
use Puli\RepositoryManager\Package\PackageManager;
use Puli\RepositoryManager\Repository\RepositoryManager;
use Puli\RepositoryManager\Util\System;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webmozart\PathUtil\Path;

/**
 * Creates environment and manager instances.
 *
 * Use this class to bootstrap the managers provided by this package:
 *
 * ```php
 * $environment = ManagerFactory::createProjectEnvironment(getcwd());
 * $packageManager = ManagerFactory::createPackageManager($environment);
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
 *    of a Puli project. Use {@link createPackageFileManager()} to create it.
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
    public static function createGlobalEnvironment()
    {
        $dispatcher = new EventDispatcher();
        $homeDir = System::parseHomeDirectory();

        System::denyWebAccess($homeDir);

        return new GlobalEnvironment(
            $homeDir,
            self::createConfigFileStorage(),
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
    public static function createProjectEnvironment($rootDir)
    {
        $dispatcher = new EventDispatcher();
        $homeDir = System::parseHomeDirectory();

        System::denyWebAccess($homeDir);

        return new ProjectEnvironment(
            $homeDir,
            $rootDir,
            self::createConfigFileStorage(),
            self::createPackageFileStorage($dispatcher),
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
    public static function createConfigFileManager(GlobalEnvironment $environment)
    {
        return new ConfigFileManager(
            $environment,
            self::createConfigFileStorage()
        );
    }

    /**
     * Creates a package file manager.
     *
     * @param ProjectEnvironment $environment The project environment.
     *
     * @return PackageFileManager The created package file manager.
     */
    public static function createPackageFileManager(ProjectEnvironment $environment)
    {
        return new PackageFileManager(
            $environment,
            self::createPackageFileStorage($environment->getEventDispatcher()),
            self::createConfigFileManager($environment)
        );
    }

    /**
     * Creates a package manager.
     *
     * @param ProjectEnvironment $environment The project environment.
     *
     * @return PackageManager The package manager.
     */
    public static function createPackageManager(ProjectEnvironment $environment)
    {
        return new PackageManager(
            $environment,
            self::createPackageFileStorage($environment->getEventDispatcher())
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
    public static function createRepositoryManager(ProjectEnvironment $environment, PackageManager $packageManager = null)
    {
        $packageManager = $packageManager ?: self::createPackageManager($environment);

        return new RepositoryManager($environment, $packageManager->getPackages());
    }

    /**
     * Creates the resource repository for a Puli project.
     *
     * @param ProjectEnvironment $environment The project environment.
     *
     * @return ResourceRepositoryInterface The resource repository.
     */
    public static function createRepository(ProjectEnvironment $environment)
    {
        $repoPath = Path::makeAbsolute(
            $environment->getConfig()->get(Config::READ_REPO),
            $environment->getRootDirectory()
        );

        if (!file_exists($repoPath)) {
            $manager = self::createRepositoryManager($environment);
            $manager->dumpRepository();
        }

        return include $repoPath;
    }

    private static function createConfigFileStorage()
    {
        return new ConfigFileStorage(
            new ConfigJsonReader(),
            new ConfigJsonWriter()
        );
    }

    private static function createPackageFileStorage(EventDispatcherInterface $dispatcher)
    {
        return new PackageFileStorage(
            new PackageJsonReader(),
            new PackageJsonWriter(),
            $dispatcher
        );
    }

    private function __construct() {}
}
