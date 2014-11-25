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

use Puli\PackageManager\Config\GlobalConfigStorage;
use Puli\PackageManager\Config\Reader\ConfigJsonReader;
use Puli\PackageManager\Config\Writer\ConfigJsonWriter;
use Puli\PackageManager\Manager\ProjectConfigManager;
use Puli\PackageManager\Manager\GlobalConfigManager;
use Puli\PackageManager\Environment\GlobalEnvironment;
use Puli\PackageManager\Environment\ProjectEnvironment;
use Puli\PackageManager\Manager\PackageManager;
use Puli\PackageManager\Package\Config\PackageConfigStorage;
use Puli\PackageManager\Package\Config\Reader\PackageJsonReader;
use Puli\PackageManager\Package\Config\Writer\PackageJsonWriter;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfigStorage;
use Puli\PackageManager\Repository\Config\Reader\RepositoryJsonReader;
use Puli\PackageManager\Repository\Config\Writer\RepositoryJsonWriter;
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
     * Creates a package manager for a Puli project.
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
            self::createRepositoryConfigStorage()
        );
    }

    /**
     * Creates the resource repository for a Puli project.
     *
     * @param ProjectEnvironment $environment The project environment.
     *
     * @return ResourceRepositoryInterface The resource repository.
     */
    public static function createResourceRepository(ProjectEnvironment $environment)
    {
        $repoPath = Path::makeAbsolute(
            $environment->getRootPackageConfig()->getGeneratedResourceRepository(),
            $environment->getRootDirectory()
        );

        if (!file_exists($repoPath)) {
            $manager = self::createPackageManager($environment);
            $manager->generateResourceRepository();
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

    private static function createRepositoryConfigStorage()
    {
        return new PackageRepositoryConfigStorage(
            new RepositoryJsonReader(),
            new RepositoryJsonWriter()
        );
    }

    private static function createPackageConfigStorage(EventDispatcherInterface $dispatcher)
    {
        return new PackageConfigStorage(
            new PackageJsonReader(),
            new PackageJsonWriter(),
            $dispatcher
        );
    }

    private function __construct() {}
}
