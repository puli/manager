<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Module;

use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\InvalidConfigException;
use Webmozart\Expression\Expression;

/**
 * Manages the module repository of a Puli project.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ModuleManager
{
    /**
     * Returns the manager's context.
     *
     * @return ProjectContext The project context.
     */
    public function getContext();

    /**
     * Installs the module at the given path in the repository.
     *
     * @param string      $installPath   The path to the module.
     * @param string|null $name          The module name or `null` if the name
     *                                   should be read from the module's
     *                                   puli.json.
     * @param string      $installerName The name of the installer.
     * @param string      $env           The environment to install the module
     *                                   in.
     *
     * @throws InvalidConfigException If the module is not configured correctly.
     * @throws NameConflictException  If the module has the same name as another
     *                                loaded module.
     */
    public function installModule($installPath, $name = null, $installerName = InstallInfo::DEFAULT_INSTALLER_NAME, $env = Environment::PROD);

    /**
     * Renames the module with the given name.
     *
     * @param string $name    The module name.
     * @param string $newName The new module name.
     *
     * @throws NoSuchModuleException If the module was not found.
     * @throws NameConflictException If a module with the new name exists
     *                               already.
     */
    public function renameModule($name, $newName);

    /**
     * Removes the module with the given name.
     *
     * @param string $name The module name.
     */
    public function removeModule($name);

    /**
     * Removes all modules matching the given expression.
     *
     * If no matching modules are found, this method does nothing.
     *
     * @param Expression $expr The search criteria.
     */
    public function removeModules(Expression $expr);

    /**
     * Removes all modules.
     *
     * If matching modules are found, this method does nothing.
     */
    public function clearModules();

    /**
     * Returns a module by name.
     *
     * @param string $name The module name.
     *
     * @return Module The module.
     *
     * @throws NoSuchModuleException If the module was not found.
     */
    public function getModule($name);

    /**
     * Returns the root module.
     *
     * @return RootModule The root module.
     */
    public function getRootModule();

    /**
     * Returns all installed modules.
     *
     * @return ModuleCollection The installed modules.
     */
    public function getModules();

    /**
     * Returns all modules matching the given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return ModuleCollection The modules matching the expression.
     */
    public function findModules(Expression $expr);

    /**
     * Returns whether the manager has the module with the given name.
     *
     * @param string $name The module name.
     *
     * @return bool Whether the manager has a module with that name.
     */
    public function hasModule($name);

    /**
     * Returns whether the manager has any modules.
     *
     * You can optionally pass an expression to check whether the manager has
     * modules matching the expression.
     *
     * @param Expression|null $expr The search criteria.
     *
     * @return bool Returns `true` if the manager has modules and `false`
     *              otherwise. If an expression was passed, this method only
     *              returns `true` if the manager has modules matching the
     *              expression.
     */
    public function hasModules(Expression $expr = null);
}
