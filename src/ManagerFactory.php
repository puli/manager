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

use Puli\PackageManager\Config\GlobalConfigManager;
use Puli\PackageManager\Config\GlobalConfigStorage;
use Puli\PackageManager\Config\GlobalEnvironment;
use Puli\PackageManager\Config\Reader\ConfigJsonReader;
use Puli\PackageManager\Config\Writer\ConfigJsonWriter;
use Puli\PackageManager\Package\Config\PackageConfigStorage;
use Puli\PackageManager\Package\Config\Reader\PuliJsonReader;
use Puli\PackageManager\Package\Config\Writer\PuliJsonWriter;
use Puli\PackageManager\Package\InstallFile\InstallFileStorage;
use Puli\PackageManager\Package\InstallFile\Reader\PackagesJsonReader;
use Puli\PackageManager\Package\InstallFile\Writer\PackagesJsonWriter;
use Puli\PackageManager\Package\PackageManager;
use Puli\PackageManager\Project\ProjectConfigManager;
use Puli\PackageManager\Project\ProjectEnvironment;
use Puli\PackageManager\Repository\RepositoryManager;
use Puli\PackageManager\Util\System;
use Puli\Repository\ResourceRepositoryInterface;
use Puli\Util\Path;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
 * The factory creates three kinds of managers:
 *
 *  * The "global config manager" allows you to modify entries of the
 *    "config.json" file in the home directory. Global config managers are
 *    created with {@link createGlobalConfigManager()}.
 *
 *  * The "project config manager" manages modifications to the "puli.json" file
 *    of a Puli project. Use {@link createProjectConfigManager()} to create it.
 *
 *  * The "package manager" manages the administration of the package
 *    repository of a Puli project. A package manager can be created with
 *    {@link createPackageManager()}.
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
            self::createGlobalConfigStorage(),
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
            self::createGlobalConfigStorage(),
            self::createPackageConfigStorage($dispatcher),
            $dispatcher
        );
    }

    /**
     * Creates a global configuration manager.
     *
     * @param GlobalEnvironment $environment The global environment.
     *
     * @return GlobalConfigManager The created configuration manager.
     */
    public static function createGlobalConfigManager(GlobalEnvironment $environment)
    {
        return new GlobalConfigManager(
            $environment,
            self::createGlobalConfigStorage()
        );
    }

    /**
     * Creates a project configuration manager.
     *
     * @param ProjectEnvironment $environment The project environment.
     *
     * @return ProjectConfigManager The created configuration manager.
     */
    public static function createProjectConfigManager(ProjectEnvironment $environment)
    {
        return new ProjectConfigManager(
            $environment,
            self::createPackageConfigStorage($environment->getEventDispatcher()),
            self::createGlobalConfigManager($environment)
        );
    }

    /**
     * Creates the package manager for a Puli project.
     *
     * @param ProjectEnvironment $environment The project environment.
     *
     * @return PackageManager The package manager.
     */
    public static function createPackageManager(ProjectEnvironment $environment)
    {
        return new PackageManager(
            $environment,
            self::createPackageConfigStorage($environment->getEventDispatcher()),
            self::createInstallFileStorage()
        );
    }

    /**
     * Creates the resource repository manager for a Puli project.
     *
     * @param ProjectEnvironment $environment The project environment.
     *
     * @return RepositoryManager The repository manager.
     */
    public static function createRepositoryManager(ProjectEnvironment $environment)
    {
        return new RepositoryManager($environment, self::createPackageManager($environment)->getPackages());
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
            $environment->getRootPackageConfig()->getGeneratedResourceRepository(),
            $environment->getRootDirectory()
        );

        if (!file_exists($repoPath)) {
            $manager = self::createRepositoryManager($environment);
            $manager->dumpRepository();
        }

        return include $repoPath;
    }

    private static function createGlobalConfigStorage()
    {
        return new GlobalConfigStorage(
            new ConfigJsonReader(),
            new ConfigJsonWriter()
        );
    }

    private static function createInstallFileStorage()
    {
        return new InstallFileStorage(
            new PackagesJsonReader(),
            new PackagesJsonWriter()
        );
    }

    private static function createPackageConfigStorage(EventDispatcherInterface $dispatcher)
    {
        return new PackageConfigStorage(
            new PuliJsonReader(),
            new PuliJsonWriter(),
            $dispatcher
        );
    }

    private function __construct() {}
}
