<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api;

use JsonSchema\Uri\UriRetriever;
use LogicException;
use Psr\Log\LoggerInterface;
use Puli\Discovery\Api\Discovery;
use Puli\Discovery\Api\EditableDiscovery;
use Puli\Manager\Api\Asset\AssetManager;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Config\ConfigFileManager;
use Puli\Manager\Api\Context\Context;
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Factory\FactoryManager;
use Puli\Manager\Api\Installation\InstallationManager;
use Puli\Manager\Api\Installer\InstallerManager;
use Puli\Manager\Api\Module\ModuleManager;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Api\Module\RootModuleFileManager;
use Puli\Manager\Api\Repository\RepositoryManager;
use Puli\Manager\Api\Server\ServerManager;
use Puli\Manager\Api\Storage\Storage;
use Puli\Manager\Assert\Assert;
use Puli\Manager\Asset\DiscoveryAssetManager;
use Puli\Manager\Config\ConfigFileConverter;
use Puli\Manager\Config\ConfigFileManagerImpl;
use Puli\Manager\Config\DefaultConfig;
use Puli\Manager\Config\EnvConfig;
use Puli\Manager\Discovery\DiscoveryManagerImpl;
use Puli\Manager\Factory\FactoryManagerImpl;
use Puli\Manager\Factory\Generator\DefaultGeneratorRegistry;
use Puli\Manager\Filesystem\FilesystemStorage;
use Puli\Manager\Installation\InstallationManagerImpl;
use Puli\Manager\Installer\ModuleFileInstallerManager;
use Puli\Manager\Json\ChainVersioner;
use Puli\Manager\Json\JsonConverterProvider;
use Puli\Manager\Json\JsonStorage;
use Puli\Manager\Json\LocalUriRetriever;
use Puli\Manager\Module\Migration\ModuleFile10To20Migration;
use Puli\Manager\Module\ModuleFileConverter;
use Puli\Manager\Module\ModuleManagerImpl;
use Puli\Manager\Module\RootModuleFileConverter;
use Puli\Manager\Module\RootModuleFileManagerImpl;
use Puli\Manager\Php\ClassWriter;
use Puli\Manager\Repository\RepositoryManagerImpl;
use Puli\Manager\Server\ModuleFileServerManager;
use Puli\Manager\Util\System;
use Puli\Repository\Api\EditableRepository;
use Puli\Repository\Api\ResourceRepository;
use Puli\UrlGenerator\Api\UrlGenerator;
use Puli\UrlGenerator\DiscoveryUrlGenerator;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webmozart\Expression\Expr;
use Webmozart\Json\Conversion\JsonConverter;
use Webmozart\Json\JsonDecoder;
use Webmozart\Json\JsonEncoder;
use Webmozart\Json\JsonValidator;
use Webmozart\Json\Migration\MigratingConverter;
use Webmozart\Json\Migration\MigrationManager;
use Webmozart\Json\Validation\ValidatingConverter;
use Webmozart\Json\Versioning\JsonVersioner;
use Webmozart\Json\Versioning\SchemaUriVersioner;
use Webmozart\Json\Versioning\VersionFieldVersioner;
use Webmozart\PathUtil\Path;

/**
 * The Puli service locator.
 *
 * Use this class to access the managers provided by this module:
 *
 * ```php
 * $puli = new Puli(getcwd());
 * $puli->start();
 *
 * $moduleManager = $puli->getModuleManager();
 * ```
 *
 * The `Puli` class either operates in the global or a project context:
 *
 *  * The "global context" is not tied to a specific root module. A global
 *    context only loads the settings of the "config.json" file in the home
 *    directory. The `Puli` class operates in the global context if no
 *    project root directory is passed to the constructor. In the global
 *    context, only the global config file manager is available.
 *  * The "project context" is tied to a specific Puli project. You need to
 *    pass the path to the project's root directory to the constructor or to
 *    {@link setRootDirectory()}. The configuration of the "puli.json" file in
 *    the root directory is used to configure the managers.
 *
 * The `Puli` class creates four kinds of managers:
 *
 *  * The "config file manager" allows you to modify entries of the
 *    "config.json" file in the home directory.
 *  * The "module file manager" manages modifications to the "puli.json" file
 *    of a Puli project.
 *  * The "module manager" manages the module repository of a Puli project.
 *  * The "repository manager" manages the resource repository of a Puli
 *    project.
 *  * The "discovery manager" manages the resource discovery of a Puli project.
 *
 * The home directory is read from the context variable "PULI_HOME".
 * If this variable is not set, the home directory defaults to:
 *
 *  * `$HOME/.puli` on Linux, where `$HOME` is the context variable
 *    "HOME".
 *  * `$APPDATA/Puli` on Windows, where `$APPDATA` is the context
 *    variable "APPDATA".
 *
 * If none of these variables can be found, an exception is thrown.
 *
 * A .htaccess file is put into the home directory to protect it from web
 * access.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Puli
{
    /**
     * @var string|null
     */
    private $rootDir;

    /**
     * @var string
     */
    private $env;

    /**
     * @var EventDispatcherInterface|null
     */
    private $dispatcher;

    /**
     * @var Context|ProjectContext
     */
    private $context;

    /**
     * @var ResourceRepository
     */
    private $repo;

    /**
     * @var Discovery
     */
    private $discovery;

    /**
     * @var object
     */
    private $factory;

    /**
     * @var FactoryManager
     */
    private $factoryManager;

    /**
     * @var ConfigFileManager
     */
    private $configFileManager;

    /**
     * @var RootModuleFileManager
     */
    private $rootModuleFileManager;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var RepositoryManager
     */
    private $repositoryManager;

    /**
     * @var DiscoveryManager
     */
    private $discoveryManager;

    /**
     * @var AssetManager
     */
    private $assetManager;

    /**
     * @var InstallationManager
     */
    private $installationManager;

    /**
     * @var InstallerManager
     */
    private $installerManager;

    /**
     * @var ServerManager
     */
    private $serverManager;

    /**
     * @var UrlGenerator
     */
    private $urlGenerator;

    /**
     * @var Storage|null
     */
    private $storage;

    /**
     * @var JsonStorage|null
     */
    private $jsonStorage;

    /**
     * @var ConfigFileConverter|null
     */
    private $configFileConverter;

    /**
     * @var JsonConverter|null
     */
    private $moduleFileConverter;

    /**
     * @var JsonConverter|null
     */
    private $legacyModuleFileConverter;

    /**
     * @var JsonConverter|null
     */
    private $rootModuleFileConverter;

    /**
     * @var JsonConverter|null
     */
    private $legacyRootModuleFileConverter;

    /**
     * @var MigrationManager|null
     */
    private $moduleFileMigrationManager;

    /**
     * @var JsonEncoder
     */
    private $jsonEncoder;

    /**
     * @var JsonDecoder
     */
    private $jsonDecoder;

    /**
     * @var JsonValidator
     */
    private $jsonValidator;

    /**
     * @var JsonVersioner
     */
    private $jsonVersioner;

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
    private $pluginsEnabled = true;

    /**
     * Parses the system context for a home directory.
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
            // Context variable was not found -> no home directory
            // This happens often on web servers where the home directory is
            // not set manually
            return null;
        }
    }

    /**
     * Creates a new instance for the given Puli project.
     *
     * @param string|null $rootDir The root directory of the Puli project.
     *                             If none is passed, the object operates in
     *                             the global context. You can set or switch
     *                             the root directories later on by calling
     *                             {@link setRootDirectory()}.
     * @param string      $env     One of the {@link Environment} constants.
     *
     * @see Puli, start()
     */
    public function __construct($rootDir = null, $env = Environment::DEV)
    {
        $this->setRootDirectory($rootDir);
        $this->setEnvironment($env);
    }

    /**
     * Starts the service container.
     */
    public function start()
    {
        if ($this->started) {
            throw new LogicException('Puli is already started');
        }

        if (null !== $this->rootDir) {
            $this->context = $this->createProjectContext($this->rootDir, $this->env);
            $bootstrapFile = $this->context->getConfig()->get(Config::BOOTSTRAP_FILE);

            // Run the project's bootstrap file to enable project-specific
            // autoloading
            if (null !== $bootstrapFile) {
                // Backup autoload functions of the PHAR
                $autoloadFunctions = spl_autoload_functions();

                foreach ($autoloadFunctions as $autoloadFunction) {
                    spl_autoload_unregister($autoloadFunction);
                }

                // Add project-specific autoload functions
                require_once Path::makeAbsolute($bootstrapFile, $this->rootDir);

                // Prepend autoload functions of the PHAR again
                // This is needed if the user specific autoload functions were
                // added with $prepend=true (as done by Composer)
                // Classes in the PHAR should always take precedence
                for ($i = count($autoloadFunctions) - 1; $i >= 0; --$i) {
                    spl_autoload_register($autoloadFunctions[$i], true, true);
                }
            }
        } else {
            $this->context = $this->createGlobalContext();
        }

        $this->dispatcher = $this->context->getEventDispatcher();
        $this->started = true;

        // Start plugins once the container is running
        if ($this->rootDir && $this->pluginsEnabled) {
            $this->activatePlugins();
        }
    }

    /**
     * Returns whether the service container is started.
     *
     * @return bool Returns `true` if the container is started and `false`
     *              otherwise.
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * Sets the root directory of the managed Puli project.
     *
     * @param string|null $rootDir The root directory of the managed Puli
     *                             project or `null` to start Puli outside of a
     *                             specific project.
     */
    public function setRootDirectory($rootDir)
    {
        if ($this->started) {
            throw new LogicException('Puli is already started');
        }

        Assert::nullOrDirectory($rootDir);

        $this->rootDir = $rootDir ? Path::canonicalize($rootDir) : null;
    }

    /**
     * Sets the environment of the managed Puli project.
     *
     * @param string $env One of the {@link Environment} constants.
     */
    public function setEnvironment($env)
    {
        if ($this->started) {
            throw new LogicException('Puli is already started');
        }

        Assert::oneOf($env, Environment::all(), 'The environment must be one of: %2$s. Got: %s');

        $this->env = $env;
    }

    /**
     * Retturns the environment of the managed Puli project.
     *
     * @return string One of the {@link Environment} constants.
     */
    public function getEnvironment()
    {
        return $this->env;
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
     * @param LoggerInterface $logger The logger to use.
     */
    public function setLogger(LoggerInterface $logger)
    {
        if ($this->started) {
            throw new LogicException('Puli is already started');
        }

        $this->logger = $logger;
    }

    /**
     * Returns the logger.
     *
     * @return LoggerInterface The logger.
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Sets the event dispatcher to use.
     *
     * @param EventDispatcherInterface $dispatcher The event dispatcher to use.
     */
    public function setEventDispatcher(EventDispatcherInterface $dispatcher)
    {
        if ($this->started) {
            throw new LogicException('Puli is already started');
        }

        $this->dispatcher = $dispatcher;
    }

    /**
     * Returns the used event dispatcher.
     *
     * @return EventDispatcherInterface|null The used logger.
     */
    public function getEventDispatcher()
    {
        return $this->dispatcher;
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
     * Returns the context.
     *
     * @return Context|ProjectContext The context.
     */
    public function getContext()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        return $this->context;
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

        if (!$this->context instanceof ProjectContext) {
            return null;
        }

        if (!$this->repo) {
            $this->repo = $this->getFactory()->createRepository();
        }

        return $this->repo;
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

        if (!$this->context instanceof ProjectContext) {
            return null;
        }

        if (!$this->discovery) {
            $this->discovery = $this->getFactory()->createDiscovery($this->getRepository());
        }

        return $this->discovery;
    }

    /**
     * @return object
     */
    public function getFactory()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->factory && $this->context instanceof ProjectContext) {
            $this->factory = $this->getFactoryManager()->createFactory();
        }

        return $this->factory;
    }

    /**
     * @return FactoryManager
     */
    public function getFactoryManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->factoryManager && $this->context instanceof ProjectContext) {
            $this->factoryManager = new FactoryManagerImpl(
                $this->context,
                new DefaultGeneratorRegistry(),
                new ClassWriter()
            );

            // Don't set via the constructor to prevent cyclic dependencies
            $this->factoryManager->setModules($this->getModuleManager()->getModules());
            $this->factoryManager->setServers($this->getServerManager()->getServers());
        }

        return $this->factoryManager;
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

        if (!$this->configFileManager && $this->context->getHomeDirectory()) {
            $this->configFileManager = new ConfigFileManagerImpl(
                $this->context,
                $this->getJsonStorage()
            );
        }

        return $this->configFileManager;
    }

    /**
     * Returns the root module file manager.
     *
     * @return RootModuleFileManager The module file manager.
     */
    public function getRootModuleFileManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->rootModuleFileManager && $this->context instanceof ProjectContext) {
            $this->rootModuleFileManager = new RootModuleFileManagerImpl(
                $this->context,
                $this->getJsonStorage()
            );
        }

        return $this->rootModuleFileManager;
    }

    /**
     * Returns the module manager.
     *
     * @return ModuleManager The module manager.
     */
    public function getModuleManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->moduleManager && $this->context instanceof ProjectContext) {
            $this->moduleManager = new ModuleManagerImpl(
                $this->context,
                $this->getJsonStorage()
            );
        }

        return $this->moduleManager;
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

        if (!$this->repositoryManager && $this->context instanceof ProjectContext) {
            $this->repositoryManager = new RepositoryManagerImpl(
                $this->context,
                $this->getRepository(),
                $this->getModuleManager()->findModules(Expr::method('isEnabled', Expr::same(true))),
                $this->getJsonStorage()
            );
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

        if (!$this->discoveryManager && $this->context instanceof ProjectContext) {
            $this->discoveryManager = new DiscoveryManagerImpl(
                $this->context,
                $this->getDiscovery(),
                $this->getModuleManager()->findModules(Expr::method('isEnabled', Expr::same(true))),
                $this->getJsonStorage(),
                $this->logger
            );
        }

        return $this->discoveryManager;
    }

    /**
     * Returns the asset manager.
     *
     * @return AssetManager The asset manager.
     */
    public function getAssetManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->assetManager && $this->context instanceof ProjectContext) {
            $this->assetManager = new DiscoveryAssetManager(
                $this->getDiscoveryManager(),
                $this->getServerManager()->getServers()
            );
        }

        return $this->assetManager;
    }

    /**
     * Returns the installation manager.
     *
     * @return InstallationManager The installation manager.
     */
    public function getInstallationManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->installationManager && $this->context instanceof ProjectContext) {
            $this->installationManager = new InstallationManagerImpl(
                $this->getContext(),
                $this->getRepository(),
                $this->getServerManager()->getServers(),
                $this->getInstallerManager()
            );
        }

        return $this->installationManager;
    }

    /**
     * Returns the installer manager.
     *
     * @return InstallerManager The installer manager.
     */
    public function getInstallerManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->installerManager && $this->context instanceof ProjectContext) {
            $this->installerManager = new ModuleFileInstallerManager(
                $this->getRootModuleFileManager(),
                $this->getModuleManager()->getModules()
            );
        }

        return $this->installerManager;
    }

    /**
     * Returns the server manager.
     *
     * @return ServerManager The server manager.
     */
    public function getServerManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->serverManager && $this->context instanceof ProjectContext) {
            $this->serverManager = new ModuleFileServerManager(
                $this->getRootModuleFileManager(),
                $this->getInstallerManager()
            );
        }

        return $this->serverManager;
    }

    /**
     * Returns the resource URL generator.
     *
     * @return UrlGenerator The resource URL generator.
     */
    public function getUrlGenerator()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->urlGenerator && $this->context instanceof ProjectContext) {
            $urlFormats = array();
            foreach ($this->getServerManager()->getServers() as $server) {
                $urlFormats[$server->getName()] = $server->getUrlFormat();
            }

            $this->urlGenerator = new DiscoveryUrlGenerator($this->getDiscovery(), $urlFormats);
        }

        return $this->urlGenerator;
    }

    /**
     * Returns the file storage.
     *
     * @return Storage The storage.
     */
    public function getStorage()
    {
        if (!$this->storage) {
            $this->storage = new FilesystemStorage();
        }

        return $this->storage;
    }

    /**
     * Returns the configuration file serializer.
     *
     * @return ConfigFileConverter The configuration file serializer.
     */
    public function getConfigFileConverter()
    {
        if (!$this->configFileConverter) {
            $this->configFileConverter = new ConfigFileConverter();
        }

        return $this->configFileConverter;
    }

    /**
     * Returns the module file converter.
     *
     * @return JsonConverter The module file converter.
     */
    public function getModuleFileConverter()
    {
        if (!$this->moduleFileConverter) {
            $this->moduleFileConverter = $this->createValidatingConverter(
                new ModuleFileConverter($this->getJsonVersioner())
            );
        }

        return $this->moduleFileConverter;
    }

    /**
     * Returns the module file serializer with support for legacy versions.
     *
     * @return JsonConverter The module file converter.
     */
    public function getLegacyModuleFileConverter()
    {
        if (!$this->legacyModuleFileConverter) {
            $this->legacyModuleFileConverter = $this->createValidatingConverter(
                new MigratingConverter(
                    $this->getModuleFileConverter(),
                    ModuleFileConverter::VERSION,
                    $this->getModuleFileMigrationManager()
                ),
                function (stdClass $jsonData) {
                    if (isset($jsonData->{'$schema'})) {
                        return $jsonData->{'$schema'};
                    }

                    // BC with 1.0
                    return 'http://puli.io/schema/1.0/manager/module';
                }
            );
        }

        return $this->legacyModuleFileConverter;
    }

    /**
     * Returns the module file converter.
     *
     * @return JsonConverter The module file converter.
     */
    public function getRootModuleFileConverter()
    {
        if (!$this->rootModuleFileConverter) {
            $this->rootModuleFileConverter = $this->createValidatingConverter(
                new RootModuleFileConverter($this->getJsonVersioner())
            );
        }

        return $this->rootModuleFileConverter;
    }

    /**
     * Returns the module file serializer with support for legacy versions.
     *
     * @return JsonConverter The module file converter.
     */
    public function getLegacyRootModuleFileConverter()
    {
        if (!$this->legacyRootModuleFileConverter) {
            $this->legacyRootModuleFileConverter = $this->createValidatingConverter(
                new MigratingConverter(
                    $this->getRootModuleFileConverter(),
                    RootModuleFileConverter::VERSION,
                    $this->getModuleFileMigrationManager()
                ),
                function (stdClass $jsonData) {
                    if (isset($jsonData->{'$schema'})) {
                        return $jsonData->{'$schema'};
                    }

                    // BC with 1.0
                    return 'http://puli.io/schema/1.0/manager/module';
                }
            );
        }

        return $this->legacyRootModuleFileConverter;
    }

    /**
     * Returns the JSON encoder.
     *
     * @return JsonEncoder The JSON encoder.
     */
    public function getJsonEncoder()
    {
        if (!$this->jsonEncoder) {
            $this->jsonEncoder = new JsonEncoder();
            $this->jsonEncoder->setPrettyPrinting(true);
            $this->jsonEncoder->setEscapeSlash(false);
            $this->jsonEncoder->setTerminateWithLineFeed(true);
        }

        return $this->jsonEncoder;
    }

    /**
     * Returns the JSON decoder.
     *
     * @return JsonDecoder The JSON decoder.
     */
    public function getJsonDecoder()
    {
        if (!$this->jsonDecoder) {
            $this->jsonDecoder = new JsonDecoder();
        }

        return $this->jsonDecoder;
    }

    /**
     * Returns the JSON validator.
     *
     * @return JsonValidator The JSON validator.
     */
    public function getJsonValidator()
    {
        if (!$this->jsonValidator) {
            $uriRetriever = new UriRetriever();

            // Load puli.io schemas from the schema/ directory
            $uriRetriever->setUriRetriever(new LocalUriRetriever());

            $this->jsonValidator = new JsonValidator(null, $uriRetriever);
        }

        return $this->jsonValidator;
    }

    private function activatePlugins()
    {
        foreach ($this->context->getRootModuleFile()->getPluginClasses() as $pluginClass) {
            $this->validatePluginClass($pluginClass);

            /** @var PuliPlugin $plugin */
            $plugin = new $pluginClass();
            $plugin->activate($this);
        }
    }

    private function createGlobalContext()
    {
        $baseConfig = new DefaultConfig();
        $homeDir = self::parseHomeDirectory();

        if (null !== $configFile = $this->loadConfigFile($homeDir, $baseConfig)) {
            $baseConfig = $configFile->getConfig();
        }

        $config = new EnvConfig($baseConfig);

        return new Context($homeDir, $config, $configFile, $this->dispatcher);
    }

    /**
     * Creates the context of a Puli project.
     *
     * The home directory is read from the context variable "PULI_HOME".
     * If this variable is not set, the home directory defaults to:
     *
     *  * `$HOME/.puli` on Linux, where `$HOME` is the context variable
     *    "HOME".
     *  * `$APPDATA/Puli` on Windows, where `$APPDATA` is the context
     *    variable "APPDATA".
     *
     * If none of these variables can be found, an exception is thrown.
     *
     * A .htaccess file is put into the home directory to protect it from web
     * access.
     *
     * @param string $rootDir The path to the project.
     *
     * @return ProjectContext The project context.
     */
    private function createProjectContext($rootDir, $env)
    {
        Assert::fileExists($rootDir, 'Could not load Puli context: The root %s does not exist.');
        Assert::directory($rootDir, 'Could not load Puli context: The root %s is a file. Expected a directory.');

        $baseConfig = new DefaultConfig();
        $homeDir = self::parseHomeDirectory();

        if (null !== $configFile = $this->loadConfigFile($homeDir, $baseConfig)) {
            $baseConfig = $configFile->getConfig();
        }

        // Create a storage without the factory manager
        $jsonStorage = new JsonStorage(
            $this->getStorage(),
            new JsonConverterProvider($this),
            $this->getJsonEncoder(),
            $this->getJsonDecoder()
        );

        $rootDir = Path::canonicalize($rootDir);
        $rootFilePath = $this->rootDir.'/puli.json';

        try {
            $rootModuleFile = $jsonStorage->loadRootModuleFile($rootFilePath, $baseConfig);
        } catch (FileNotFoundException $e) {
            $rootModuleFile = new RootModuleFile(null, $rootFilePath, $baseConfig);
        }

        $config = new EnvConfig($rootModuleFile->getConfig());

        return new ProjectContext($homeDir, $rootDir, $config, $rootModuleFile, $configFile, $this->dispatcher, $env);
    }

    /**
     * Decorates a converter with a {@link ValidatingConverter}.
     *
     * @param JsonConverter        $innerConverter The converter to decorate.
     * @param string|callable|null $schema         The schema.
     *
     * @return ValidatingConverter The decorated converter.
     */
    private function createValidatingConverter(JsonConverter $innerConverter, $schema = null)
    {
        return new ValidatingConverter($innerConverter, $schema, $this->getJsonValidator());
    }

    /**
     * Returns the JSON file storage.
     *
     * @return JsonStorage The JSON file storage.
     */
    private function getJsonStorage()
    {
        if (!$this->jsonStorage) {
            $this->jsonStorage = new JsonStorage(
                $this->getStorage(),
                new JsonConverterProvider($this),
                $this->getJsonEncoder(),
                $this->getJsonDecoder(),
                $this->getFactoryManager()
            );
        }

        return $this->jsonStorage;
    }

    /**
     * Returns the JSON versioner.
     *
     * @return JsonVersioner The JSON versioner.
     */
    private function getJsonVersioner()
    {
        if (!$this->jsonVersioner) {
            $this->jsonVersioner = new ChainVersioner(array(
                // check the schema of the "$schema" field by default
                new SchemaUriVersioner(),
                // fall back to the "version" field for 1.0
                new VersionFieldVersioner(),
            ));
        }

        return $this->jsonVersioner;
    }

    /**
     * Returns the migration manager for module files.
     *
     * @return MigrationManager The migration manager.
     */
    private function getModuleFileMigrationManager()
    {
        if (!$this->moduleFileMigrationManager) {
            $this->moduleFileMigrationManager = new MigrationManager(array(
                new ModuleFile10To20Migration(),
            ), $this->getJsonVersioner());
        }

        return $this->moduleFileMigrationManager;
    }

    /**
     * Validates the given plugin class name.
     *
     * @param string $pluginClass The fully qualified name of a plugin class.
     */
    private function validatePluginClass($pluginClass)
    {
        if (!class_exists($pluginClass)) {
            throw new InvalidConfigException(sprintf(
                'The plugin class %s does not exist.',
                $pluginClass
            ));
        }

        if (!in_array('Puli\Manager\Api\PuliPlugin', class_implements($pluginClass))) {
            throw new InvalidConfigException(sprintf(
                'The plugin class %s must implement PuliPlugin.',
                $pluginClass
            ));
        }
    }

    private function loadConfigFile($homeDir, Config $baseConfig)
    {
        if (null === $homeDir) {
            return null;
        }

        Assert::fileExists($homeDir, 'Could not load Puli context: The home directory %s does not exist.');
        Assert::directory($homeDir, 'Could not load Puli context: The home directory %s is a file. Expected a directory.');

        // Create a storage without the factory manager
        $jsonStorage = new JsonStorage(
            $this->getStorage(),
            new JsonConverterProvider($this),
            $this->getJsonEncoder(),
            $this->getJsonDecoder()
        );

        $configPath = Path::canonicalize($homeDir).'/config.json';

        try {
            return $jsonStorage->loadConfigFile($configPath, $baseConfig);
        } catch (FileNotFoundException $e) {
            // It's ok if no config.json exists. We'll work with
            // DefaultConfig instead
            return null;
        }
    }
}
