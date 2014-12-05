Changelog
=========

* 1.0.0-alpha2 (@release_date@)

 * added `$name` argument to `PackageManager::installPackage()`
 * added `getName()` and `setName()` to `PackageMetadata`
 * removed `setNew()` and `isNew()` from `PackageMetadata`
 * changed `Package` to prefer the name of the metadata over the name set in the
   package file
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
 * moved `TagDescriptor`, `ResourceDescriptor` and `PackageMetadata` to
   `Puli\RepositoryManager\Package` namespace

* 1.0.0-alpha1 (2014-12-03)

 * first alpha release
