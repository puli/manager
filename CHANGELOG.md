Changelog
=========

* 1.0.0-next (@release_date@)

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
 * moved `CompositeKeyStore` to `Puli\RepositoryManager\Util` namespace
 * moved `BindingStore` to `Puli\RepositoryManager\Discovery\BindingDescriptorStore`
 * moved `BindingTypeStore` to `Puli\RepositoryManager\Discovery\BindingTypeDescriptorStore`

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
 * added `CompositeKeyStore`
 * added `BindingDescriptorStore`
 * added `BindingTypeDescriptorStore`
 * added `TypeNotEnabledException`
 * added `UnsupportedVersionException`
 * added `PackageState`
 * added `RecursivePathIterator`
 * added `RecursivePathsIterator`
 * added `NoSuchMappingException`
 * added `RepositoryUpdater`
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
