<?php

/**
 * Manages the package repository.
 *
 * Many parts of this class are inspired by Composer.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */

namespace Puli\PackageManager;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Config\Reader\ConfigJsonReader;
use Puli\PackageManager\Config\Reader\GlobalConfigReaderInterface;
use Puli\PackageManager\Config\Writer\ConfigJsonWriter;
use Puli\PackageManager\Config\Writer\GlobalConfigWriterInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The system environment of the package manager.
 *
 * This class provides access to system-wide services. Use it to change the
 * global configuration or to access system-wide variables like Puli's home
 * directory.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PuliEnvironment
{
    /**
     * @var string
     */
    private $homeDir;

    /**
     * @var GlobalConfig
     */
    private $globalConfig;

    /**
     * @var GlobalConfigReaderInterface
     */
    private $configReader;

    /**
     * @var GlobalConfigWriterInterface
     */
    private $configWriter;

    /**
     * Creates the Puli environment from the system's environment variables.
     *
     * This method scans the environment variables "PULI_HOME", "HOME" and
     * "APPDATA" to determine Puli's home directory:
     *
     *  * If "PULI_HOME" is found, that directory is used.
     *  * If "HOME" is found, a directory ".puli" is created inside. This
     *    variable contains the path of the user's home directory by default
     *    on Unix based systems.
     *  * If "APPDATA" is found, a directory "Puli" is created inside. This
     *    variable contains the path of the application data by default on
     *    Windows.
     *
     * A .htaccess file is put into Puli's home directory to protect it from
     * web access.
     *
     * @throws BootstrapException If the home directory is not found or is not
     *                            a directory.
     * @throws InvalidConfigException If the global configuration is invalid.
     */
    public static function createFromSystem()
    {
        $homeDir = self::parseHomeDirectory();

        self::denyWebAccess($homeDir);

        return new self($homeDir, new ConfigJsonReader(), new ConfigJsonWriter());
    }

    private static function parseHomeDirectory()
    {
        if ($value = getenv('PULI_HOME')) {
            $homeDir = $value;
            $env = 'PULI_HOME';
        } elseif ($value = getenv('HOME')) {
            $homeDir = $value;
            $env = 'HOME';
        } elseif ($value = getenv('APPDATA')) {
            $homeDir = $value;
            $env = 'APPDATA';
        } else {
            throw new BootstrapException(sprintf(
                'Either the environment variable PULI_HOME or %s must be set for '.
                'Puli to run.',
                defined('PHP_WINDOWS_VERSION_MAJOR') ? 'APPDATA' : 'HOME'
            ));
        }

        $homeDir = strtr($homeDir, array('\\' => '/'));

        if (is_file($homeDir)) {
            throw new BootstrapException(sprintf(
                'The home path %s defined in the environment variable %s '.
                'points to a file. Expected a directory path.',
                $homeDir,
                $env
            ));
        }

        switch ($env) {
            case 'PULI_HOME':
                return $homeDir; // user defined
            case 'HOME':
                return $homeDir.'/.puli'; // Linux/Mac
            default:
                return $homeDir.'/Puli'; // Windows
        }
    }

    private static function denyWebAccess($directory)
    {
        if (!file_exists($directory.'/.htaccess')) {
            if (!is_dir($directory)) {
                $filesystem = new Filesystem();
                $filesystem->mkdir($directory);
            }

            @file_put_contents($directory.'/.htaccess', 'Deny from all');
        }
    }

    /**
     * Creates the Puli environment.
     *
     * The passed home directory will be be scanned for a file "config.json".
     * If that file exists, it is loaded into memory. Use the other methods in
     * this class to read and modify the configuration values.
     *
     * @param string                      $homeDir      The path to Puli's home directory.
     * @param GlobalConfigReaderInterface $configReader The global config file reader.
     * @param GlobalConfigWriterInterface $configWriter The global config file writer
     *
     * @throws InvalidConfigException If the global configuration is invalid.
     */
    public function __construct(
        $homeDir,
        GlobalConfigReaderInterface $configReader,
        GlobalConfigWriterInterface $configWriter
    )
    {
        $this->homeDir = $homeDir;
        $this->configReader = $configReader;
        $this->configWriter = $configWriter;

        $configPath = $this->homeDir.'/config.json';

        try {
            // Don't use file_exists() in order to let the config reader decide
            // when to throw FileNotFoundException
            $this->globalConfig = $this->configReader->readGlobalConfig($configPath);
        } catch (FileNotFoundException $e) {
            $this->globalConfig = new GlobalConfig($configPath);
        }
    }

    /**
     * Returns the user's home directory used by Puli.
     *
     * @return string The path to the home directory.
     */
    public function getHomeDirectory()
    {
        return $this->homeDir;
    }

    /**
     * Returns the global configuration.
     *
     * @return GlobalConfig The global configuration.
     */
    public function getGlobalConfig()
    {
        return $this->globalConfig;
    }

    /**
     * Installs a plugin class in the global configuration.
     *
     * The plugin class must be passed as fully-qualified name of a class that
     * implements {@link \Puli\PackageManager\Plugin\PluginInterface}. Plugin
     * constructors must not have mandatory parameters.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     *
     * @throws InvalidConfigException If a class is not found, is not a class,
     *                                does not implement
     *                                {@link \Puli\PackageManager\Plugin\PluginInterface}
     *                                or has required constructor parameters.
     */
    public function installGlobalPluginClass($pluginClass)
    {
        if ($this->globalConfig->hasPluginClass($pluginClass)) {
            // Already installed
            return;
        }

        $this->globalConfig->addPluginClass($pluginClass);

        $this->configWriter->writeGlobalConfig($this->globalConfig, $this->globalConfig->getPath());
    }

    /**
     * Returns whether a plugin class is installed in the global configuration.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     *
     * @return bool Whether the plugin class is installed.
     *
     * @see installGlobalPluginClass()
     */
    public function isGlobalPluginClassInstalled($pluginClass)
    {
        return $this->globalConfig->hasPluginClass($pluginClass);
    }

    /**
     * Returns all globally installed plugin classes.
     *
     * @return string[] The fully qualified plugin class names.
     *
     * @see installGlobalPluginClass()
     */
    public function getGlobalPluginClasses()
    {
        return $this->globalConfig->getPluginClasses();
    }
}
