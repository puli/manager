Changelog
=========

* 1.0.0-beta11 (@release_date@)

 * renamed `Package` to `Module` everywhere
 * renamed `PackageCollection` to `ModuleList`
 * removed `MigrationException`
 * removed `JsonMigration`
 * removed `MigrationManager`
 * replaced `PackageFileSerializer` and `PackageJsonSerializer` by 
   `ModuleFileConverter` and `RootModuleFileConverter`
 * replaced `ConfigFileSerializer` and `ConfigJsonSerializer` by 
   `ConfigFileConverter`
 * replaced `ConfigFileStorage` and `ModuleFileStorage` by `JsonStorage`
 * renamed `Puli` to `Container`

* 1.0.0-beta10 (2016-01-14)

 * made compatible with Symfony 3.0
 * fixed `puli.json` schema file `package-schema-1.0.json`
 * fixed: disabled binding UUIDs are now removed if the referenced bindings
   don't exist
 * added `DiscoveryManager::removeObsoleteDisabledBindingDescriptors()`
 * fixed: a meaningful exception is now thrown if an `AssetMapping` is added
   but the "puli/url-generator" package is not installed
 * fixed: packages whose puli.json does not exist now return `null` from
   `Package::getPackageFile()`
 * replaced `StorageException` by `ReadException` and `WriteException`
 * added `getPackageOrder()` to generated `GeneratedPuliFactory` class
 * added support for `ChangeStream` and implementations
 * added support for `JsonRepository`
 * added support for `OptimizedJsonRepository`
 * added support for `JsonDiscovery`
 * added `Config::CHANGE_STREAM`
 * added `Config::CHANGE_STREAM_TYPE`
 * added `Config::CHANGE_STREAM_PATH`
 * added `Config::CHANGE_STREAM_STORE`
 * added `Config::CHANGE_STREAM_STORE_TYPE`
 * added `Config::CHANGE_STREAM_STORE_PATH`
 * added `Config::CHANGE_STREAM_STORE_HOST`
 * added `Config::CHANGE_STREAM_STORE_PORT`
 * added `Config::CHANGE_STREAM_STORE_BUCKET`
 * added `Config::CHANGE_STREAM_STORE_CACHE`
 * added `Config::DISCOVERY_PATH`

* 1.0.0-beta9 (2015-10-06)

 * fixed regression in `BindingExpressionBuilder`

* 1.0.0-beta8 (2015-10-05)

 * added `PuliEvents::POST_ADD_ASSET_MAPPING` and `PuliEvents::POST_REMOVE_ASSET_MAPPING`
 * removed constants from `AssetMapping` to match changed webmozart/expression API
 * removed constants from `BindingDescriptor` to match changed webmozart/expression API
 * removed constants from `BindingTypeDescriptor` to match changed webmozart/expression API
 * removed constants from `InstallerDescriptor` to match changed webmozart/expression API
 * removed constants from `Package` to match changed webmozart/expression API
 * removed constants from `PathMapping` to match changed webmozart/expression API
 * removed constants from `Server` to match changed webmozart/expression API
 * removed `BindingDescriptor::match()`
 * removed `BindingTypeDescriptor::match()`
 * removed `InstallerDescriptor::match()`
 * removed `Package::match()`
 * removed `PathMapping::match()`
 * removed `Server::match()`
 * changed `BindingDescriptor::__construct()` to accept a `Binding` instance
 * removed `BindingDescriptor::getQuery()`
 * removed `BindingDescriptor::getLanguage()`
 * removed `BindingDescriptor::getParameterValues()`
 * removed `BindingDescriptor::getParameterValue()`
 * removed `BindingDescriptor::hasParameterValues()`
 * removed `BindingDescriptor::hasParameterValue()`
 * renamed `BindingDescriptor::getViolations()` to `getLoadErrors()`
 * added `BindingDescriptor::getBinding()`
 * changed `BindingTypeDescriptor::__construct()` to accept a `BindingType` instance
 * renamed `BindingTypeDescriptor::getName()` to `getTypeName()`
 * added `BindingTypeDescriptor::getType()`
 * added `BindingTypeDescriptor::getParameterDescriptions()`
 * added `BindingTypeDescriptor::getParameterDescription()`
 * added `BindingTypeDescriptor::hasParameterDescriptions()`
 * added `BindingTypeDescriptor::hasParameterDescription()`
 * removed `BindingTypeDescriptor::toBindingType()`
 * removed `BindingTypeDescriptor::getParameters()`
 * removed `BindingTypeDescriptor::getParameter()`
 * removed `BindingTypeDescriptor::hasParameter()`
 * removed `BindingTypeDescriptor::hasRequiredParameters()`
 * removed `BindingTypeDescriptor::hasOptionalParameters()`
 * removed `BindingTypeDescriptor::getParameterValues()`
 * removed `BindingTypeDescriptor::getParameterValue()`
 * removed `BindingTypeDescriptor::hasParameterValues()`
 * removed `BindingTypeDescriptor::hasParameterValue()`
 * removed `BindingParameterDescriptor`
 * renamed `DiscoveryManager::addRootBindingType()` to `addRootTypeDescriptor()`
 * renamed `DiscoveryManager::removeRootBindingType()` to `removeRootTypeDescriptor()`
 * renamed `DiscoveryManager::removeRootBindingTypes()` to `removeRootTypeDescriptors()`
 * renamed `DiscoveryManager::clearRootBindingTypes()` to `clearRootTypeDescriptors()`
 * renamed `DiscoveryManager::getRootBindingType()` to `getRootTypeDescriptor()`
 * renamed `DiscoveryManager::getRootBindingTypes()` to `getRootTypeDescriptors()`
 * renamed `DiscoveryManager::hasRootBindingType()` to `hasRootTypeDescriptor()`
 * renamed `DiscoveryManager::hasRootBindingTypes()` to `hasRootTypeDescriptors()`
 * renamed `DiscoveryManager::getBindingType()` to `getTypeDescriptor()`
 * renamed `DiscoveryManager::getBindingTypes()` to `getTypeDescriptors()`
 * renamed `DiscoveryManager::findBindingTypes()` to `findTypeDescriptors()`
 * renamed `DiscoveryManager::hasBindingType()` to `hasTypeDescriptor()`
 * renamed `DiscoveryManager::hasBindingTypes()` to `hasTypeDescriptors()`
 * renamed `DiscoveryManager::addRootBinding()` to `addRootBindingDescriptor()`
 * renamed `DiscoveryManager::removeRootBinding()` to `removeRootBindingDescriptor()`
 * renamed `DiscoveryManager::removeRootBindings()` to `removeRootBindingDescriptors()`
 * renamed `DiscoveryManager::clearRootBindings()` to `clearRootBindingDescriptors()`
 * renamed `DiscoveryManager::getRootBinding()` to `getRootBindingDescriptor()`
 * renamed `DiscoveryManager::getRootBindings()` to `getRootBindingDescriptors()`
 * renamed `DiscoveryManager::findRootBindings()` to `findRootBindingDescriptors()`
 * renamed `DiscoveryManager::hasRootBinding()` to `hasRootBindingDescriptor()`
 * renamed `DiscoveryManager::hasRootBindings()` to `hasRootBindingDescriptors()`
 * renamed `DiscoveryManager::enableBinding()` to `enableBindingDescriptor()`
 * renamed `DiscoveryManager::disableBinding()` to `disableBindingDescriptor()`
 * renamed `DiscoveryManager::getBinding()` to `getBindingDescriptor()`
 * renamed `DiscoveryManager::getBindings()` to `getBindingDescriptors()`
 * renamed `DiscoveryManager::findBindings()` to `findBindingDescriptors()`
 * renamed `DiscoveryManager::hasBinding()` to `hasBindingDescriptor()`
 * renamed `DiscoveryManager::hasBindings()` to `hasBindingDescriptors()`
 * added `RootPackageFileManager::migrate()`
 * added `MigrationManager`
 * added `JsonMigration`
 * added `MigrationException`
 * added `PackageFile::DEFAULT_VERSION`
 * added `PackageFile::getVersion()`
 * added `PackageFile::setVersion()`
 * removed `UnsupportedVersionException::versionTooHigh()`
 * removed `UnsupportedVersionException::versionTooLow()`
 * added `UnsupportedVersionException::forVersion()`
 * fixed to register PHAR autoloaders before project-specific autoloaders

* 1.0.0-beta7 (2015-08-24)

 * renamed `GlobalEnvironment` to `Context`
 * renamed `ProjectEnvironment` to `ProjectContext`
 * added `Environment`
 * removed `InstallInfo::isDevDependency()`
 * removed `InstallInfo::setDevDependency()`
 * added `InstallInfo::getEnvironment()`
 * added `InstallInfo::setEnvironment()`
 * added `Puli::setEnvironment()` and `Puli::getEnvironment()`
 * added `ProjectContext::getEnvironment()`
 * fixed minimum package versions in composer.json
 * upgraded to webmozart/glob 3.1

* 1.0.0-beta6 (2015-08-12)

 * added `Storage` and `FilesystemStorage`
 * renamed `Puli\Manager\Api\IOException` to `Puli\Manager\Api\Storage\StorageException`
 * replaced `PackageFileReader` and `PackageFileWriter` by `PackageFileSerializer`
 * replaced `ConfigFileReader` and `ConfigFileWriter` by `ConfigFileSerializer`
 * bindings are now always enabled unless they are explicitly disabled
 * removed `BindingState::UNDECIDED`
 * removed `InstallInfo::*Enabled*()` methods
 * improved speed by 99% through optimized `SyncRepositoryPath` algorithm
 * added config key "bootstrap-file" which is loaded before loading plugins
 * added repository type "path-mapping"
 * added config key "repository.optimize"
 * the config key "factory.in.file" can now be set to `null` if "factory.in.class"
   is auto-loadable
 * changed default "repository.type" to "path-mapping"
 * added `$dev` parameter to `PackageManager::installPackage()`
 * added `InstallInfo::isDevDependency()` and `InstallInfo::setDevDependency()`

* 1.0.0-beta5 (2015-05-29)

 * integrated puli/asset-plugin into puli/manager
 * added `ConfigManager` interface
 * added argument `$raw` to the getters in `ConfigManager`
 * added `PuliEvents::PRE_BUILD_REPOSITORY` and `PuliEvents::POST_BUILD_REPOSITORY`
 * added `PackageManager::renamePackage()`
 * removed default servers
 * renamed `AssetMapping::getPublicPath()` to `getServerPath()`
 * renamed `InstallationParams::getPublicPath()` to `getServerPath()`
 * upgraded to webmozart/path-util 2.0
 * fixed `PackageFileInstallerManager` for unloadable packages
 * decoupled from puli/factory
 * fixed filemtime() warning when no puli.json is present in the current directory
 * removed realpath() where it doesn't work if the package is distributed in a
   PHAR

* 1.0.0-beta4 (2015-04-13)

 * separated "factory.class" and "factory.file" config keys into
   "factory.in.class", "factory.out.class", "factory.in.file" and "factory.out.file"
 * added `NO_DUPLICATE_CHECK` flag to suppress duplicate checks in
   `DiscoveryManager::addBindingType()`
 * added `NO_TYPE_CHECK` flag to suppress type checks in `DiscoveryManager::addBinding()`
 * changed boolean parameter `$required` to integer parameter `$flags` in
   `BindingParameterDescriptor::__construct()`
 * duplicate binding UUIDs are forbidden now
 * renamed `BindingState::HELD_BACK` to `BindingState::TYPE_NOT_LOADED`
 * renamed "resources" key in puli.json to "path-mappings"
 * renamed `ResourceMapping` to `PathMapping`
 * renamed `ResourceMappingState` to `PathMappingState`
 * renamed `NoSuchMappingException` to `NoSuchPathMappingException`
 * renamed `RepositoryPathConflict` to `PathConflict`
 * renamed `RepositoryManager::*ResourceMapping*()` to `RepositoryManager::*PathMapping*()`
 * changed boolean parameter `$failIfNotFound` to integer parameter `$flags` in
   `RepositoryManager::addPathMapping()`
 * made argument `$packageName` mandatory in `DiscoveryManager::getBindingType()`
 * removed `CannotEnableBindingException` and `CannotDisableBindingException`
 * replaced `BindingState::TYPE_NOT_LOADED` by `TYPE_NOT_FOUND` and
   `TYPE_NOT_ENABLED`
 * `RepositoryManager::addPathMapping()` now throws an exception if the same
   repository path is already mapped in the root package
 * renamed `DiscoveryManager::addBindingType()` to `addRootBindingType()`
 * renamed `DiscoveryManager::removeBindingType()` to `removeRootBindingType()`
 * added `DiscoveryManager::getRootBindingType()`
 * added `DiscoveryManager::getRootBindingTypes()`
 * added `DiscoveryManager::hasRootBindingType()`
 * added `DiscoveryManager::hasRootBindingTypes()`
 * renamed `DiscoveryManager::addBinding()` to `addRootBinding()`
 * renamed `DiscoveryManager::removeBinding()` to `removeRootBinding()`
 * added `DiscoveryManager::getRootBinding()`
 * added `DiscoveryManager::getRootBindings()`
 * added `DiscoveryManager::hasRootBinding()`
 * added `DiscoveryManager::hasRootBindings()`
 * renamed `RepositoryManager::addPathMapping()` to `addRootPathMapping()`
 * renamed `RepositoryManager::removePathMapping()` to `removeRootPathMapping()`
 * added `RepositoryManager::getRootPathMapping()`
 * added `RepositoryManager::getRootPathMappings()`
 * added `RepositoryManager::hasRootPathMapping()`
 * added `RepositoryManager::hasRootPathMappings()`
 * added `RepositoryManager::hasPathMappings()`
 * added `RepositoryManager::findPathMappings()`
 * added `$packageName` argument to `RepositoryManager::getPathMapping()`
 * added `$packageName` argument to `RepositoryManager::hasPathMapping()`
 * removed arguments from `RepositoryManager::getPathMappings()`
 * removed `$code` arguments from exception factory methods
 * added `Config::clear()`
 * added `Config::isEmpty()`
 * added `ConfigFileManager::clearConfigKeys()`
 * added `ConfigFileManager::hasConfigKeys()`
 * added `PackageCollection::clear()`
 * added `PackageManager::clearPackages()`
 * added `RootPackageFile::clearInstallInfos()`
 * added `RootPackageFile::getInstallInfos()`
 * added `RootPackageFile::hasInstallInfos()`
 * added `DiscoveryManager::clearRootBindingTypes()`
 * added `DiscoveryManager::clearRootBindings()`
 * fixed restoring of conflicts when `RepositoryManager::removeRootPathMapping()` fails
 * added `RepositoryManager::clearRootPathMappings()`
 * fixed `Package::__construct()` when neither `PackageFile` nor `InstallInfo`
   are passed
 * `RepositoryManager::addRootPathMapping()` now accepts the flags
   `OVERRIDE` and `IGNORE_FILE_NOT_FOUND`
 * renamed `DiscoveryManager::NO_DUPLICATE_CHECK` to `OVERRIDE`
 * split `DiscoveryManager::NO_TYPE_CHECK` into `IGNORE_TYPE_NOT_FOUND` and
   `IGNORE_TYPE_NOT_ENABLED`
 * added `RootPackageFileManager::removePluginClasses()`
 * fixed `RootPackageFileManager` methods when saving fails
 * added optional argument `$expr` to `RootPackageFileManager::hasPluginClasses()`
 * added `RootPackageFileManager::findPluginClasses()`
 * added optional argument `$expr` to `RootPackageFileManager::hasExtraKeys()`
 * changed argument `$keys` to `$expr` for `RootPackageFileManager::removeExtraKeys()`
 * added `RootPackageFileManager::findExtraKeys()`
 * added `Config::replace()`
 * changed argument `$keys` to `$expr` for `ConfigFileManager::removeConfigKeys()`
 * added optional argument `$expr` to `ConfigFileManager::hasConfigKeys()`
 * changed argument `$pattern` to `$expr` for `ConfigFileManager::findConfigKeys()`
 * added `DiscoveryManager::removeRootBindingTypes()`
 * added `DiscoveryManager::removeRootBindings()`
 * added `RepositoryManager::removeRootPathMappings()`
 * added `PackageCollection::merge()`
 * added `PackageCollection::replace()`
 * added `PackageManager::removePackages()`
 * fixed `PackageManager::clearPackages()`
 * added `DiscoveryManager::findRootBindingTypes()`
 * added `DiscoveryManager::findRootBindings()`
 * added `RepositoryManager::findRootPathMappings()`
      
* 1.0.0-beta3 (2015-03-19)

 * added `BindingState::DUPLICATE` to output of `BindingState::all()`
 * renamed `ManagerEvents` to `PuliEvents` and moved to 
   `Puli\RepositoryManager\Api\Event\` namespace
 * added `Puli::getRepository()` shortcut
 * added `Puli::getDiscovery()` shortcut
 * added `Puli::getEventDispatcher()` shortcut
 * removed the arguments of `DiscoveryManager::getBindings()`
 * changed the arguments of `DiscoveryManager::findBindings()` to `Criteria`
 * added `BindingDescriptor::match()`
 * added `DiscoveryManager::getBinding()`
 * added `DiscoveryManager::hasBinding()`
 * added `DiscoveryManager::hasBindings()`
 * removed the arguments of `DiscoveryManager::getBindingTypes()`
 * changed the arguments of `DiscoveryManager::findBindingTypes()` to `Criteria`
 * added `BindingTypeDescriptor::match()`
 * added `DiscoveryManager::getBindingType()`
 * added `DiscoveryManager::hasBindingType()`
 * added `DiscoveryManager::hasBindingTypes()`
 * removed the arguments of `PackageManager::getPackages()`
 * removed `PackageManager::getPackagesByInstaller()`
 * added `Package::match()`
 * added `PackageManager::findPackages()`
 * added `PackageManager::hasPackages()`
 * moved `Puli` to `Api` namespace
 * added `Puli::start()` which must be called explicitly to start the service
   container
 * added `Puli::enablePlugins()`
 * added `Puli::disablePlugins()`
 * added `Puli::arePluginsEnabled()`
 * added `Puli::isStarted()`
 * added support for extra keys
 * added `OverrideGraph::forPackages()`
 * removed superfluous `PackageManager::isPackageInstalled()`. Use
   `PackageManager::hasPackages()` with an `Expression` instead
 * renamed `BindingState::DUPLICATE` to `OVERRIDDEN`
 * renamed `Generator` namespace to `Factory`
 * turned `GlobalEnvironment` and `ProjectEnvironment` interfaces into classes
 * removed `ProjectEnvironment::getRepository()` and `ProjectEnvironment::getDiscovery()`
 * added `FactoryManager`
 * added `Puli::getFactory()`
 * added `Puli::getFactoryManager()`
 * added `PuliEvents::GENERATE_FACTORY` which is thrown whenever the factory
   class is generated
 * removed `PuliEvents::LOAD_PACKAGE_FILE` and`PuliEvents::SAVE_PACKAGE_FILE`
 * moved code to `Puli\Manager` namespace
 * renamed package to "puli/manager"
 * added more plugin management methods to `RootPackageFile` and
   `RootPackageFileManager`

* 1.0.0-beta2 (2015-01-27)

 * moved public classes to `Api` sub-namespace
 * removed dependency to beberlei/assert
 * package load errors are not logged anymore
 * renamed `InstallInfo::get/setInstaller()` to `get/setInstallerName()`
 * renamed `InstallInfo::DEFAULT_INSTALLER` to `DEFAULT_INSTALLER_NAME`
 * multi-valued keys in puli.json are now sorted to avoid changed files in the
   VCS when they haven't really changed
 * made `RepositoryManager::loadPackages()` private
 * made `PackageManager::loadPackages()` private
 * `PackageManager::getPackages()` returns packages with any state now by default
 * `DiscoveryManager::getBindingTypes()` returns types with any state now by default
 * `DiscoveryManager::getBindings()` returns bindings with any state now by default
 * added `AlreadyLoadedException`
 * added `NotLoadedException`
 * added optional argument `$failIfNotFound` to `RepositoryManager::addResourceMapping()`
 * added optional argument `$state` to `RepositoryManager::getResourceMappings()`
 * added `RepositoryPathConflict`
 * added `ResourceMappingState`
 * added `ResourceMapping::load()`
 * added `ResourceMapping::unload()`
 * added `ResourceMapping::getPathReferences()`
 * added `ResourceMapping::getContainingPackage()`
 * added `ResourceMapping::getLoadErrors()`
 * added `ResourceMapping::addConflict()`
 * added `ResourceMapping::removeConflict()`
 * added `ResourceMapping::getConflicts()`
 * added `ResourceMapping::getConflictingPackages()`
 * added `ResourceMapping::getConflictingMappings()`
 * added `ResourceMapping::getState()`
 * added `ResourceMapping::isLoaded()`
 * added `ResourceMapping::isEnabled()`
 * added `ResourceMapping::isNotFound()`
 * added `ResourceMapping::isConflicting()`
 * `RepositoryManager::getResourceMappings()` does not throw exceptions anymore
   if mapped paths/packages are not found or have a conflict. Instead, you can
   access load errors and conflicts via `ResourceMapping::getLoadErrors()` and
   `ResourceMapping::getConflicts()`
 * removed unused `ResourceDefinitionException`
 * added `Config::contains()`
 * added `ConfigFileManager::hasConfigKey()`
 * moved `$uuid` argument to last position in `BindingDescriptor::__construct()`
 * removed `BindingDescriptor::create()`
 * removed `BindingDescriptor::resetState()`
 * removed `BindingDescriptor::refreshState()`
 * removed `BindingState::NOT_LOADED`
 * removed `BindingState::detect()`
 * added `BindingDescriptor::load()`
 * added `BindingDescriptor::unload()`
 * added `BindingDescriptor::isLoaded()`
 * added `BindingDescriptor::getContainingPackage()`
 * added `BindingDescriptor::markDuplicate()`
 * added `BindingDescriptor::isDuplicate()`
 * added `BindingState::DUPLICATE`
 * removed `BindingTypeState::NOT_LOADED`
 * removed `BindingTypeState::detect()`
 * removed `BindingTypeDescriptor::resetState()`
 * removed `BindingTypeDescriptor::refreshState()`
 * added `BindingTypeDescriptor::load()`
 * added `BindingTypeDescriptor::unload()`
 * added `BindingTypeDescriptor::isLoaded()`
 * added `BindingTypeDescriptor::getContainingPackage()`
 * added `BindingTypeDescriptor::markDuplicate()`
 * removed `PackageState::NOT_LOADED`
 * removed `PackageState::detect()`
 * changed `DiscoveryManager::addBinding()` to accept a `BindingDescriptor` instance
 * added `BindingDescriptor::listPathMappings()`
 * added `BindingDescriptor::listRepositoryPaths()`
 * added `BindingDescriptor::getTypeDescriptor()`
 * changed `PackageFile::setOverriddenPackages()` to only accept arrays
 * added `PackageFile::removeOverriddenPackage()`
 * added `PackageFile::hasOverriddenPackage()`
 * added `RepositoryPathConflict::addMappings()`
 * removed `BindingDescriptor::isIgnored()` and `BindingState::IGNORED`. Bindings
   with duplicate types have the state `BindingState::HELD_BACK` now
 * removed `CannotDisableBindingException::duplicateType()`
 * removed `CannotEnableBindingException::duplicateType()`
 * changed default value of `$state` in `PackageManager::getPackagesByInstaller()`
   to `null`
 * replaced `ManagerFactory` by the `Puli` class
 * renamed `ManagerPlugin` to `PuliPlugin`
 * changed `PuliPlugin::activate()` to receive the `Puli` instance
 * renamed `Package::getLoadError()` to `getLoadErrors()`
 * added `Config::getBaseConfig()`
 * added "discovery.store.type" values "php-redis", "predis" and "riak"
 * renamed `Config::DISCOVERY_STORE_SERVER` to `DISCOVERY_STORE_HOST`
 * added `Config::DISCOVERY_STORE_BUCKET`

* 1.0.0-beta (2015-01-12)

 * removed `Interface` suffix of all interfaces
 * added `$name` argument to `PackageManager::installPackage()`
 * added default returned value to `Config::get()` and `Config::getRaw()` 
 * added `GlobalEnvironment::getDiscoveryStorage()`
 * added composite key handling to `Config`
 * added `Assert`
 * added `PuliFactoryGenerator`
 * added `BuildRecipe`
 * added `BuildRecipeProvider`
 * added `ProviderFactory`
 * added `PuliFactoryGenerator`
 * added `KeyValueStoreDiscoveryRecipeProvider`
 * added `ArrayStoreRecipeProvider`
 * added `JsonFileStoreRecipeProvider`
 * added `MemcachedStoreRecipeProvider`
 * added `MemcacheStoreRecipeProvider`
 * added `NullStoreRecipeProvider`
 * added `FilesystemRepositoryRecipeProvider`
 * added `getRepository()` and `getDiscovery()` to `ProjectEnvironment`
 * added `PackageConflict`
 * added `PackageConflictDetector`
 * added `PackageConflictException`
 * added `DiscoveryManager`
 * added `DiscoveryNotEmptyException`
 * added `BindingDescriptor`
 * added `BindingParameterDescriptor`
 * added `BindingState`
 * added `BindingTypeDescriptor`
 * added `BindingTypeState`
 * added `CannotDisableBindingException`
 * added `CannotEnableBindingException`
 * added `NoSuchBindingException`
 * added `TwoDimensionalHashMap`
 * added `BindingDescriptorStore`
 * added `BindingTypeDescriptorStore`
 * added `TypeNotEnabledException`
 * added `UnsupportedVersionException`
 * added `PackageState`
 * added `RecursivePathIterator`
 * added `RecursivePathsIterator`
 * added `NoSuchMappingException`
 * added `UpdateRepository`
 * added `DistinguishedName`
 * added `Config::FACTORY`
 * added `Config::FACTORY_AUTO_GENERATE`
 * added `Config::FACTORY_CLASS`
 * added `Config::FACTORY_FILE`
 * added `Config::REPOSITORY`
 * added `Config::REPOSITORY_TYPE`
 * added `Config::REPOSITORY_SYMLINK`
 * added `Config::DISCOVERY`
 * added `Config::DISCOVERY_TYPE`
 * added `Config::DISCOVERY_STORE`
 * added `Config::DISCOVERY_STORE_TYPE`
 * added `Config::DISCOVERY_STORE_PATH`
 * added `Config::DISCOVERY_STORE_SERVER`
 * added `Config::DISCOVERY_STORE_PORT`
 * added `Config::DISCOVERY_STORE_CACHE`
 * added `$name` argument to constructor of `InstallInfo`
 * added optional `$baseConfig` argument to `ConfigFileStorage::loadConfigFile()` 
 * added optional `$baseConfig` argument to `ConfigFileSerializer::readConfigFile()` 
 * added optional `$baseConfig` argument to `ConfigFile` constructor
 * added `Config::getKeys()`
 * added optional `$default` argument to `Config::get()` and `Config::getRaw()`
 * added `Config::toFlatArray()`
 * added `Config::toFlatRawArray()`
 * added `OverrideGraph::addPackageNames()`
 * added `Package::DEFAULT_NAME`
 * added `Package::getInstallInfo()`
 * added `Package::getLoadError()`
 * added `Package::getState()`
 * added `Package::resetState()`
 * added `Package::refreshState()`
 * added `Package::isLoaded()`
 * added `Package::isEnabled()`
 * added `Package::isNotFound()`
 * added `Package::isNotLoadable()`
 * added `PackageCollection::getRootPackageName()`
 * added `PackageCollection::getInstalledPackages()`
 * added `PackageCollection::getPackageNames()`
 * added `PackageFile::addOverriddenPackage()`
 * added `PackageFile::addResourceMappings()`
 * added `PackageFile::getResourceMapping()`
 * added `PackageFile::hasResourceMapping()`
 * added `PackageFile::removeResourceMapping()`
 * added `PackageFile::getBindingDescriptors()`
 * added `PackageFile::getBindingDescriptor()`
 * added `PackageFile::addBindingDescriptor()`
 * added `PackageFile::removeBindingDescriptor()`
 * added `PackageFile::hasBindingDescriptor()`
 * added `PackageFile::clearBindingDescriptors()`
 * added `PackageFile::getTypeDescriptors()`
 * added `PackageFile::getTypeDescriptor()`
 * added `PackageFile::hasTypeDescriptor()`
 * added `PackageFile::addTypeDescriptor()`
 * added `PackageFile::removeTypeDescriptor()`
 * added `PackageFile::clearTypeDescriptors()`
 * added `RootPackageFile::getInstallInfos()`
 * added `RootPackageFile::addInstallInfo()`
 * added `RootPackageFile::removeInstallInfo()`
 * added `RootPackageFile::getInstallInfo()`
 * added `RootPackageFile::hasInstallInfo()`
 * added `PackageManager::loadPackages()`
 * added `RepositoryManager::loadPackages()`
 * added `RepositoryManager::addResourceMapping()`
 * added `RepositoryManager::removeResourceMapping()`
 * added `RepositoryManager::hasResourceMapping()`
 * added `RepositoryManager::getResourceMapping()`
 * added `RepositoryManager::getResourceMappings()`
 * added `RepositoryManager::clearRepository()`
 * added `$packageFileStorage` argument to `RepositoryManager` constructor
 * added argument `$state` to `PackageManager::getPackages()`
 * added argument `$state` to `PackageManager::getPackagesByInstaller()`
 * added puli.json keys:
   * `version`
   * `packages`
   * `bindings`
   * `binding-types`
 * added "config" options to puli.json:
   * `factory.auto-generate`
   * `factory.class`
   * `factory.file`
   * `repository.type`
   * `repository.path`
   * `repository.symlink`
   * `discovery.type`
   * `discovery.store.type`
   * `discovery.store.path`
   * `discovery.store.server`
   * `discovery.store.port`
   * `discovery.store.cache`
 * renamed `PackageFileManager` to `RootPackageFileManager`
 * renamed `ResourceDescriptor` to `ResourceMapping`
 * renamed `PackageMetadata` to `InstallInfo`
 * renamed `CycleException` to `CyclicDependencyException`
 * renamed `PackageNameGraph` to `OverrideGraph`
 * renamed `Plugin` to `ManagerPlugin`
 * renamed `Config::REPO_DUMP_DIR` to `REPOSITORY_PATH`
 * renamed puli.json key `package-order` to `override-order`
 * renamed "config" option `repo-dump-dir` in puli.json to `repository.path`
 * renamed `PackageFile::addResourceDescriptor()` to `addResourceMapping()`
 * renamed `PackageFile::getResourceDescriptors()` to `getResourceMappings()`
 * renamed `RootPackageFile::getPackageOrder()` to `getOverrideOrder()`
 * renamed `RootPackageFile::setPackageOrder()` to `setOverrideOrder()`
 * renamed `RepositoryManager::dumpRepository()` to `buildRepository()`
 * moved `CyclicDependencyException to `Puli\RepositoryManager\Conflict`
 * moved `OverrideGraph to `Puli\RepositoryManager\Conflict`
 * moved `PackageMetadata to `Puli\RepositoryManager\Package`
 * moved `PackageCollection to `Puli\RepositoryManager\Package`
 * moved `ResourceMapping to `Puli\RepositoryManager\Package`
 * moved `InstallInfo to `Puli\RepositoryManager\Package`
 * removed `InstallFile`, merged methods into `RootPackageFile`
 * removed `InstallFileStorage`
 * removed `InstallFileReader`
 * removed `InstallFileJsonReader`
 * removed `InstallFileWriter`
 * removed `InstallFileJsonWriter`
 * removed `TagDescriptor`
 * removed `Config::INSTALL_FILE`
 * removed `Config::REPO_DUMP_FILE`
 * removed `Config::REPO_FILE`
 * removed `setNew()` and `isNew()` from `InstallInfo`
 * removed `PackageManager::getInstallFile()`
 * removed puli.json keys:
   * `tags`
 * removed "config" options from puli.json:
   * `install-file`
   * `repo-dump-file`
   * `repo-file`
 * changed `Package` to prefer the name of the install info over the name set in
   the package file
 * `PackageManager` is now responsible for checking whether a package name is 
   set instead of `PackageFileStorage`
 * made the HOME/PULI_HOME/APPDATA environment variables optional in
   `GlobalEnvironment`
 * `GlobalEnvironment::getConfigFile()` may now return `null`
 * `Config::toArray()` now returns a recursive array. The old result is returned
   from `Config::toFlatArray()`
 * changed all `ManagerFactory` methods from static to concrete

* 1.0.0-alpha1 (2014-12-03)

 * first alpha release
