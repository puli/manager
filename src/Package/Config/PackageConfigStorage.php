<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\Config;

use Puli\RepositoryManager\Config\GlobalConfig;
use Puli\RepositoryManager\Event\PackageConfigEvent;
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\InvalidConfigException;
use Puli\RepositoryManager\IOException;
use Puli\RepositoryManager\ManagerEvents;
use Puli\RepositoryManager\Package\Config\Reader\PackageConfigReaderInterface;
use Puli\RepositoryManager\Package\Config\Writer\PackageConfigWriterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Loads and saves package configuration.
 *
 * This class adds a layer on top of {@link PackageConfigReaderInterface} and
 * {@link PackageConfigWriterInterface}. Any logic that is related to the
 * loading and saving of package configuration, but not directly related to the
 * reading/writing of a specific file format, is executed by this class.
 *
 * The events {@link ManagerEvents::LOAD_PACKAGE_CONFIG} and
 * {@link ManagerEvents::SAVE_PACKAGE_CONFIG} are dispatched when package
 * configuration is loaded/saved.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageConfigStorage
{
    /**
     * @var PackageConfigReaderInterface
     */
    private $reader;

    /**
     * @var PackageConfigWriterInterface
     */
    private $writer;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * Creates a new configuration manager.
     *
     * @param PackageConfigReaderInterface $reader     The reader for package config files.
     * @param PackageConfigWriterInterface $writer     The writer for package config files.
     * @param EventDispatcherInterface     $dispatcher The event dispatcher to use.
     */
    public function __construct(PackageConfigReaderInterface $reader, PackageConfigWriterInterface $writer, EventDispatcherInterface $dispatcher)
    {
        $this->reader = $reader;
        $this->writer = $writer;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Loads package configuration from a file path.
     *
     * If the file does not exist, an empty configuration is returned.
     *
     * The event {@link ManagerEvents::LOAD_PACKAGE_CONFIG} is dispatched after
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
            $config = $this->reader->readPackageConfig($path);
        } catch (FileNotFoundException $e) {
            $config = new PackageConfig(null, $path);
        }

        if ($this->dispatcher->hasListeners(ManagerEvents::LOAD_PACKAGE_CONFIG)) {
            $event = new PackageConfigEvent($config);
            $this->dispatcher->dispatch(ManagerEvents::LOAD_PACKAGE_CONFIG, $event);
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
        if ($this->dispatcher->hasListeners(ManagerEvents::SAVE_PACKAGE_CONFIG)) {
            $event = new PackageConfigEvent($config);
            $this->dispatcher->dispatch(ManagerEvents::SAVE_PACKAGE_CONFIG, $event);
        }

        $this->writer->writePackageConfig($config, $config->getPath());
    }

    /**
     * Loads root package configuration from a file path.
     *
     * If the file does not exist, an empty configuration is returned.
     *
     * The event {@link ManagerEvents::LOAD_PACKAGE_CONFIG} is dispatched after
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
            $config = $this->reader->readRootPackageConfig($path, $globalConfig);
        } catch (FileNotFoundException $e) {
            $config = new RootPackageConfig($globalConfig, null, $path);
        }

        if ($this->dispatcher->hasListeners(ManagerEvents::LOAD_PACKAGE_CONFIG)) {
            $event = new PackageConfigEvent($config);
            $this->dispatcher->dispatch(ManagerEvents::LOAD_PACKAGE_CONFIG, $event);
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
        if ($this->dispatcher->hasListeners(ManagerEvents::SAVE_PACKAGE_CONFIG)) {
            $event = new PackageConfigEvent($config);
            $this->dispatcher->dispatch(ManagerEvents::SAVE_PACKAGE_CONFIG, $event);
        }

        $this->writer->writePackageConfig($config, $config->getPath());
    }
}
