Changelog
=========

* 1.0.0-next (@release_date@)

 * integrated puli/asset-plugin in puli/manager
 * added `ConfigManager` interface
 * added argument `$raw` to the getters in `ConfigManager`

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
 * added optional `$baseConfig` argument to `ConfigFileReader::readConfigFile()` 
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
