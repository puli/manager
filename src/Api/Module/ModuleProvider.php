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

use Webmozart\Expression\Expression;

/**
 * Manages the module repository of a Puli project.
 *
 * @since  1.0
 *
 * @author Mateusz Sojda <mateusz@sojda.pl>
 */
interface ModuleProvider
{
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
