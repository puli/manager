<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Package\Config;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Event\PackageConfigEvent;
use Puli\PackageManager\Event\PackageEvents;
use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\InvalidConfigException;
use Puli\PackageManager\IOException;
use Puli\PackageManager\Package\Config\Reader\PackageConfigReaderInterface;
use Puli\PackageManager\Package\Config\Writer\PackageConfigWriterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manages the loading and saving of configuration.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageConfigStorage
{
    /**
     * @var PackageConfigReaderInterface
     */
    private $packageConfigReader;

    /**
     * @var PackageConfigWriterInterface
     */
    private $packageConfigWriter;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * Creates a new configuration manager.
     *
     * @param PackageConfigReaderInterface    $packageConfigReader    The reader for package config files.
     * @param PackageConfigWriterInterface    $packageConfigWriter    The writer for package config files.
     * @param EventDispatcherInterface        $dispatcher             The event dispatcher to use.
     */
    public function __construct(
        PackageConfigReaderInterface $packageConfigReader,
        PackageConfigWriterInterface $packageConfigWriter,
        EventDispatcherInterface $dispatcher
    )
    {
        $this->packageConfigReader = $packageConfigReader;
        $this->packageConfigWriter = $packageConfigWriter;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Loads package configuration from a file path.
     *
     * If the file does not exist, an empty configuration is returned.
     *
     * The event {@link PackageEvents::LOAD_PACKAGE_CONFIG} is dispatched after
     * loading the configuration. You can attach listeners to this event to
     * modify loaded configurations.
     *
     * Loaded package configurations must have a package name set. If none is
     * set, an exception is thrown.
     *
     * @param string $path The path to the package configuration file.
     *
     * @return PackageConfig The loaded package configuration.
     *
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function loadPackageConfig($path)
    {
        try {
            // Don't use file_exists() to decouple from the file system
            $config = $this->packageConfigReader->readPackageConfig($path);
        } catch (FileNotFoundException $e) {
            $config = new PackageConfig(null, $path);
        }

        if ($this->dispatcher->hasListeners(PackageEvents::LOAD_PACKAGE_CONFIG)) {
            $event = new PackageConfigEvent($config);
            $this->dispatcher->dispatch(PackageEvents::LOAD_PACKAGE_CONFIG, $event);
        }

        if (null === $config->getPackageName()) {
            if (isset($e)) {
                throw new InvalidConfigException(sprintf(
                    'The file %s is missing.',
                    $config->getPath()
                ), $e->getCode(), $e);
            }

            throw new InvalidConfigException(sprintf(
                'The "name" key is missing in %s.',
                $config->getPath()
            ));
        }

        return $config;
    }

    /**
     * Saves package configuration.
     *
     * The package configuration is saved to the same path that it was read from.
     *
     * @param PackageConfig $config The package configuration to save.
     *
     * @throws IOException If the configuration cannot be written.
     */
    public function savePackageConfig(PackageConfig $config)
    {
        if ($this->dispatcher->hasListeners(PackageEvents::SAVE_PACKAGE_CONFIG)) {
            $event = new PackageConfigEvent($config);
            $this->dispatcher->dispatch(PackageEvents::SAVE_PACKAGE_CONFIG, $event);
        }

        $this->packageConfigWriter->writePackageConfig($config, $config->getPath());
    }

    /**
     * Loads root package configuration from a file path.
     *
     * If the file does not exist, an empty configuration is returned.
     *
     * The event {@link PackageEvents::LOAD_PACKAGE_CONFIG} is dispatched after
     * loading the configuration. You can attach listeners to this event to
     * modify loaded configurations.
     *
     * @param string       $path         The path to the package configuration file.
     * @param GlobalConfig $globalConfig The global configuration that the root
     *                                   configuration will inherit its settings
     *                                   from.
     *
     * @return RootPackageConfig The loaded package configuration.
     *
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function loadRootPackageConfig($path, GlobalConfig $globalConfig)
    {
        try {
            // Don't use file_exists() to decouple from the file system
            $config = $this->packageConfigReader->readRootPackageConfig($path, $globalConfig);
        } catch (FileNotFoundException $e) {
            $config = new RootPackageConfig($globalConfig, null, $path);
        }

        if ($this->dispatcher->hasListeners(PackageEvents::LOAD_PACKAGE_CONFIG)) {
            $event = new PackageConfigEvent($config);
            $this->dispatcher->dispatch(PackageEvents::LOAD_PACKAGE_CONFIG, $event);
        }

        return $config;
    }

    /**
     * Saves root package configuration.
     *
     * The package configuration is saved to the same path that it was read from.
     *
     * @param RootPackageConfig $config The package configuration to save.
     *
     * @throws IOException If the configuration cannot be written.
     */
    public function saveRootPackageConfig(RootPackageConfig $config)
    {
        if ($this->dispatcher->hasListeners(PackageEvents::SAVE_PACKAGE_CONFIG)) {
            $event = new PackageConfigEvent($config);
            $this->dispatcher->dispatch(PackageEvents::SAVE_PACKAGE_CONFIG, $event);
        }

        $this->packageConfigWriter->writePackageConfig($config, $config->getPath());
    }
}
