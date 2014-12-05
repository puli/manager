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

* 1.0.0-alpha1 (2014-12-03)

 * first alpha release
