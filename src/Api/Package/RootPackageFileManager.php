<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Package;

use Puli\Manager\Api\Config\ConfigManager;
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Migration\MigrationException;
use Puli\Manager\Api\Storage\StorageException;
use Webmozart\Expression\Expression;

/**
 * Manages changes to the root package file.
 *
 * Use this class to make persistent changes to the puli.json of a project.
 * Whenever you call methods in this class, the changes will be written to disk.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface RootPackageFileManager extends ConfigManager
{
    /**
     * Returns the project context.
     *
     * @return ProjectContext The project context.
     */
    public function getContext();

    /**
     * Returns the managed package file.
     *
     * @return RootPackageFile The managed package file.
     */
    public function getPackageFile();

    /**
     * Returns the package name configured in the package file.
     *
     * @return null|string The configured package name.
     */
    public function getPackageName();

    /**
     * Sets the package name configured in the package file.
     *
     * @param string $packageName The package name.
     */
    public function setPackageName($packageName);

    /**
     * Adds a plugin class to the package file.
     *
     * The plugin class must be passed as fully-qualified name of a class that
     * implements {@link PuliPlugin}. Plugin constructors must not have
     * mandatory parameters.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     */
    public function addPluginClass($pluginClass);

    /**
     * Removes a plugin class from the package file.
     *
     * If the package file does not contain the class, this method does nothing.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     */
    public function removePluginClass($pluginClass);

    /**
     * Removes the plugin classes from the package file that match the given
     * expression.
     *
     * @param Expression $expr The search criteria.
     */
    public function removePluginClasses(Expression $expr);

    /**
     * Removes all plugin classes from the package file.
     *
     * If the package file does not contain any classes, this method does
     * nothing.
     */
    public function clearPluginClasses();

    /**
     * Returns whether the package file contains a plugin class.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     *
     * @return bool Returns `true` if the package file contains the given
     *              plugin class and `false` otherwise.
     */
    public function hasPluginClass($pluginClass);

    /**
     * Returns whether the package file contains any plugin classes.
     *
     * You can optionally pass an expression to check whether the manager has
     * plugin classes matching the expression.
     *
     * @param Expression|null $expr The search criteria.
     *
     * @return bool Returns `true` if the manager has plugin classes in the root
     *              package and `false` otherwise. If an expression is passed,
     *              this method only returns `true` if the manager has plugin
     *              classes matching the expression.
     */
    public function hasPluginClasses(Expression $expr = null);

    /**
     * Returns all installed plugin classes.
     *
     * @return string[] The fully qualified plugin class names.
     */
    public function getPluginClasses();

    /**
     * Returns all installed plugin classes matching the given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return string[] The fully qualified plugin class names matching the
     *                  expression.
     */
    public function findPluginClasses(Expression $expr);

    /**
     * Sets an extra key in the file.
     *
     * The file is saved directly after setting the key.
     *
     * @param string $key   The key name.
     * @param mixed  $value The stored value.
     *
     * @throws StorageException If the file cannot be written.
     */
    public function setExtraKey($key, $value);

    /**
     * Sets the extra keys in the file.
     *
     * The file is saved directly after setting the keys.
     *
     * @param string[] $values A list of values indexed by their key names.
     *
     * @throws StorageException If the file cannot be written.
     */
    public function setExtraKeys(array $values);

    /**
     * Removes an extra key from the file.
     *
     * The file is saved directly after removing the key.
     *
     * @param string $key The name of the removed extra key.
     *
     * @throws StorageException If the file cannot be written.
     */
    public function removeExtraKey($key);

    /**
     * Removes the extra keys from the package file that match the given
     * expression.
     *
     * The file is saved directly after removing the keys.
     *
     * @param Expression $expr The search criteria.
     *
     * @throws StorageException If the file cannot be written.
     */
    public function removeExtraKeys(Expression $expr);

    /**
     * Removes all extra keys from the file.
     *
     * The file is saved directly after removing the keys.
     *
     * @throws StorageException If the file cannot be written.
     */
    public function clearExtraKeys();

    /**
     * Returns whether an extra key exists.
     *
     * @param string $key The extra key to search.
     *
     * @return bool Returns `true` if the file contains the key and `false`
     *              otherwise.
     */
    public function hasExtraKey($key);

    /**
     * Returns whether the file contains any extra keys.
     *
     * You can optionally pass an expression to check whether the file contains
     * extra keys matching the expression.
     *
     * @param Expression|null $expr The search criteria.
     *
     * @return bool Returns `true` if the file contains extra keys and `false`
     *              otherwise. If an expression is passed, this method only
     *              returns `true` if the file contains extra keys matching the
     *              expression.
     */
    public function hasExtraKeys(Expression $expr = null);

    /**
     * Returns the value of a configuration key.
     *
     * @param string $key     The name of the extra key.
     * @param mixed  $default The value to return if the key was not set.
     *
     * @return mixed The value of the key or the default value, if none is set.
     */
    public function getExtraKey($key, $default = null);

    /**
     * Returns the values of all extra keys set in the file.
     *
     * @return array A mapping of configuration keys to values.
     */
    public function getExtraKeys();

    /**
     * Returns the values of all extra keys matching the given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return array A mapping of configuration keys to values.
     */
    public function findExtraKeys(Expression $expr);

    /**
     * Migrates the root package file to the given version.
     *
     * @param string $targetVersion The target version string.
     *
     * @throws MigrationException If the migration fails.
     * @throws StorageException   If the file cannot be written.
     */
    public function migrate($targetVersion);
}
