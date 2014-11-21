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

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Config\Reader\GlobalConfigReaderInterface;
use Puli\PackageManager\Config\Writer\GlobalConfigWriterInterface;
use Puli\PackageManager\Event\PackageConfigEvent;
use Puli\PackageManager\Event\PackageEvents;
use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\Reader\PackageConfigReaderInterface;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\Config\Writer\PackageConfigWriterInterface;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;
use Puli\PackageManager\Repository\Config\Reader\RepositoryConfigReaderInterface;
use Puli\PackageManager\Repository\Config\Writer\RepositoryConfigWriterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manages the loading and saving of configuration.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigManager
{
    /**
     * @var GlobalConfigReaderInterface
     */
    private $globalConfigReader;

    /**
     * @var GlobalConfigWriterInterface
     */
    private $globalConfigWriter;

    /**
     * @var RepositoryConfigReaderInterface
     */
    private $repositoryConfigReader;

    /**
     * @var RepositoryConfigWriterInterface
     */
    private $repositoryConfigWriter;

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
     * @param GlobalConfigReaderInterface     $globalConfigReader     The reader for global config files.
     * @param GlobalConfigWriterInterface     $globalConfigWriter     The writer for global config files.
     * @param RepositoryConfigReaderInterface $repositoryConfigReader The reader for repository config files.
     * @param RepositoryConfigWriterInterface $repositoryConfigWriter The writer for repository config files.
     * @param PackageConfigReaderInterface    $packageConfigReader    The reader for package config files.
     * @param PackageConfigWriterInterface    $packageConfigWriter    The writer for package config files.
     * @param EventDispatcherInterface        $dispatcher             The event dispatcher to use.
     */
    public function __construct(
        GlobalConfigReaderInterface $globalConfigReader,
        GlobalConfigWriterInterface $globalConfigWriter,
        RepositoryConfigReaderInterface $repositoryConfigReader,
        RepositoryConfigWriterInterface $repositoryConfigWriter,
        PackageConfigReaderInterface $packageConfigReader,
        PackageConfigWriterInterface $packageConfigWriter,
        EventDispatcherInterface $dispatcher
    )
    {
        $this->globalConfigReader = $globalConfigReader;
        $this->globalConfigWriter = $globalConfigWriter;
        $this->repositoryConfigReader = $repositoryConfigReader;
        $this->repositoryConfigWriter = $repositoryConfigWriter;
        $this->packageConfigReader = $packageConfigReader;
        $this->packageConfigWriter = $packageConfigWriter;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Loads global configuration from a file path.
     *
     * If the file does not exist, an empty configuration is returned.
     *
     * @param string $path The path to the global configuration file.
     *
     * @return GlobalConfig The loaded global configuration.
     *
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function loadGlobalConfig($path)
    {
        try {
            // Don't use file_exists() to decouple from the file system
            return $this->globalConfigReader->readGlobalConfig($path);
        } catch (FileNotFoundException $e) {
            return new GlobalConfig($path);
        }
    }

    /**
     * Saves global configuration.
     *
     * The global configuration is saved to the same path that it was read from.
     *
     * @param GlobalConfig $config The global configuration to save.
     *
     * @throws IOException If the configuration cannot be written.
     */
    public function saveGlobalConfig(GlobalConfig $config)
    {
        $this->globalConfigWriter->writeGlobalConfig($config, $config->getPath());
    }

    /**
     * Loads package repository configuration from a file path.
     *
     * If the file does not exist, an empty configuration is returned.
     *
     * @param string $path The path to the repository configuration file.
     *
     * @return PackageRepositoryConfig The loaded package repository configuration.
     *
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function loadRepositoryConfig($path)
    {
        try {
            // Don't use file_exists() to decouple from the file system
            return $this->repositoryConfigReader->readRepositoryConfig($path);
        } catch (FileNotFoundException $e) {
            return new PackageRepositoryConfig($path);
        }
    }

    /**
     * Saves package repository configuration.
     *
     * The repository configuration is saved to the same path that it was read
     * from.
     *
     * @param PackageRepositoryConfig $config The package repository
     *                                        configuration to save.
     *
     * @throws IOException If the configuration cannot be written.
     */
    public function saveRepositoryConfig(PackageRepositoryConfig $config)
    {
        $this->repositoryConfigWriter->writeRepositoryConfig($config, $config->getPath());
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
     * If the package configuration has no name set, the name is set to
     * "__root__" automatically.
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

        if (null === $config->getPackageName()) {
            $config->setPackageName('__root__');
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

        if ('__root__' === $config->getPackageName()) {
            $config->setPackageName(null);
        }

        $this->packageConfigWriter->writePackageConfig($config, $config->getPath());
    }
}
