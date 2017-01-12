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
interface ModuleManager extends ModuleProvider
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
}
