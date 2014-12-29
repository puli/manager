Changelog
=========

* 1.0.0-alpha2 (@release_date@)

 * added `$name` argument to `PackageManager::installPackage()`
 * renamed `PackageMetadata` to `InstallInfo`
 * added `$name` argument to constructor of `InstallInfo`
 * removed `setNew()` and `isNew()` from `InstallInfo`
 * changed `Package` to prefer the name of the install info over the name set in
   the package file
 * `PackageManager` is now responsible for checking whether a package name is 
   set instead of `PackageFileStorage`
 * renamed options under the "config" key of puli.json:
   * `repo-file` => `read-repo`
   * `repo-dump-file` => `write-repo`
   * `repo-dump-dir` => `dump-dir`
 * renamed constants in `Config`:
   * `REPO_FILE` => `READ_REPO`
   * `REPO_DUMP_FILE` => `WRITE_REPO`
   * `REPO_DUMP_DIR` => `DUMP_DIR`
 * moved `TagDescriptor`, `ResourceDescriptor` and `InstallInfo` to
   `Puli\RepositoryManager\Package` namespace
 * renamed `TagDescriptor` and `ResourceDescriptor` to `TagMapping` and
   `ResourceMapping`
 * moved `TagMapping` to `Puli\RepositoryManager\Tag` namespace
 * added `TagDefinition`
 * merged `InstallFile` into `RootPackageFile`
 * `TagMapping` now stores one single tag only
 * added enabled/disabled tag mappings to `InstallInfo`
 * added `TagManager`
 * added optional `$baseConfig` argument to `ConfigFileStorage::loadConfigFile()` 
   and `ConfigFileReaderInterface::readConfigFile()`
 * removed `Config::INSTALL_FILE` and the "install-file" configuration in
   puli.json and config.json
 * renamed `PluginInterface` to `ManagerPlugin`
 * removed `Interface` suffixes of all interfaces
 * made the HOME/PULI_HOME/APPDATA environment variables optional
 * renamed `installPath` to `install-path` in puli.json
 * added `bindings` and `binding-types` keys to puli.json
 * removed `tags` and `tag-definitions` keys from puli.json
 * added default returned value to `Config::get()` and `Config::getRaw()` 
 * added `GlobalEnvironment::getDiscoveryStorage()`
 * added composite key handling to `Config`
 * added `RegistryGenerator`
 * added `FactoryCode` and `FactoryCodeGenerator`
 * added `GeneratorFactory`
 * added `KeyValueStoreDiscoveryGenerator`
 * added `ArrayStoreGenerator`
 * added `FlintstoneStoreGenerator`
 * added `MemcachedStoreGenerator`
 * added `MemcacheStoreGenerator`
 * added `NullStoreGenerator`
 * added `FilesystemRepositoryGenerator`
 * added `getRepository()` and `getDiscovery()` to `ProjectEnvironment`

* 1.0.0-alpha1 (2014-12-03)

 * first alpha release
