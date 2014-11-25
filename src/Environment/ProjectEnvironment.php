<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Environment;

use Puli\PackageManager\Config\GlobalConfigStorage;
use Puli\PackageManager\Environment\GlobalEnvironment;
use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\NoDirectoryException;
use Puli\PackageManager\Package\Config\PackageConfigStorage;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Plugin\PluginInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Contains environment information of a Puli project.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ProjectEnvironment extends GlobalEnvironment
{
    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var RootPackageConfig
     */
    private $rootPackageConfig;

    /**
     * Creates the project environment.
     *
     * The passed home directory will be be scanned for a file "config.json".
     * If that file exists, it is loaded into memory. Use
     * {@link getGlobalConfig()} to access the loaded configuration.
     *
     * The passed root directory will be be scanned for a file "puli.json".
     * If that file exists, it is loaded into memory. Use
     * {@link getRootPackageConfig()} to access the loaded configuration.
     *
     * @param string                   $homeDir              The path to Puli's home directory.
     * @param GlobalConfigStorage      $rootDir              The path to the project directory.
     * @param GlobalConfigStorage      $globalConfigStorage  The global configuration storage.
     * @param PackageConfigStorage     $packageConfigStorage The package configuration storage.
     * @param EventDispatcherInterface $dispatcher           The event dispatcher.
     *
     * @throws FileNotFoundException If the home/project directory does not exist.
     * @throws NoDirectoryException If the home/project directory is not a directory.
     */
    public function __construct($homeDir, $rootDir, GlobalConfigStorage $globalConfigStorage, PackageConfigStorage $packageConfigStorage, EventDispatcherInterface $dispatcher)
    {
        if (!file_exists($rootDir)) {
            throw new FileNotFoundException(sprintf(
                'Could not load Puli environment: The root %s does not exist.',
                $rootDir
            ));
        }

        if (!is_dir($rootDir)) {
            throw new NoDirectoryException(sprintf(
                'Could not load Puli environment: The root %s is a file. '.
                'Expected a directory.',
                $rootDir
            ));
        }

        parent::__construct($homeDir, $globalConfigStorage, $dispatcher);

        $this->rootDir = $rootDir;
        $this->rootPackageConfig = $packageConfigStorage->loadRootPackageConfig(
            $rootDir.'/puli.json',
            $this->getGlobalConfig()
        );

        foreach ($this->rootPackageConfig->getPluginClasses() as $pluginClass) {
            /** @var PluginInterface $plugin */
            $plugin = new $pluginClass();
            $plugin->activate($this, $this->getEventDispatcher());
        }
    }

    /**
     * Returns the path to the project's root directory.
     *
     * @return string The root directory path.
     */
    public function getRootDirectory()
    {
        return $this->rootDir;
    }

    /**
     * Returns the root package configuration of the project.
     *
     * @return RootPackageConfig The project configuration.
     */
    public function getRootPackageConfig()
    {
        return $this->rootPackageConfig;
    }
}
