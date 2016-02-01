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

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * A collection of Puli modules.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleList implements IteratorAggregate, Countable, ArrayAccess
{
    /**
     * @var RootModule
     */
    private $rootModule;

    /**
     * @var Module[]
     */
    private $modules = array();

    public function __construct(array $modules = array())
    {
        $this->merge($modules);
    }

    /**
     * Adds a module to the collection.
     *
     * @param Module $module The added module.
     */
    public function add(Module $module)
    {
        $this->modules[$module->getName()] = $module;

        if ($module instanceof RootModule) {
            $this->rootModule = $module;
        }
    }

    /**
     * Adds multiple modules to the collection.
     *
     * @param Module[] $modules The added modules.
     */
    public function merge(array $modules)
    {
        foreach ($modules as $module) {
            $this->add($module);
        }
    }

    /**
     * Replaces the collection with the given modules.
     *
     * @param Module[] $modules The modules to set.
     */
    public function replace(array $modules)
    {
        $this->clear();
        $this->merge($modules);
    }

    /**
     * Removes a module from the collection.
     *
     * @param string $name The module name.
     */
    public function remove($name)
    {
        if ($this->rootModule && $name === $this->rootModule->getName()) {
            $this->rootModule = null;
        }

        unset($this->modules[$name]);
    }

    /**
     * Removes all modules from the collection.
     */
    public function clear()
    {
        if ($this->rootModule) {
            $this->rootModule = null;
        }

        $this->modules = array();
    }

    /**
     * Returns the module with the given name.
     *
     * @param string $name The module name.
     *
     * @return Module The module with the passed name.
     *
     * @throws NoSuchModuleException If the module was not found.
     */
    public function get($name)
    {
        if (!isset($this->modules[$name])) {
            throw new NoSuchModuleException(sprintf(
                'The module "%s" was not found.',
                $name
            ));
        }

        return $this->modules[$name];
    }

    /**
     * Returns whether a module with the given name exists.
     *
     * @param string $name The module name.
     *
     * @return bool Whether a module with this name exists.
     */
    public function contains($name)
    {
        return isset($this->modules[$name]);
    }

    /**
     * Returns the root module.
     *
     * If the collection contains no root module, `null` is returned.
     *
     * @return RootModule|null The root module or `null` if none exists.
     */
    public function getRootModule()
    {
        return $this->rootModule;
    }

    /**
     * Returns the name of the root module.
     *
     * If the collection contains no root module, `null` is returned.
     *
     * @return string|null The root module name or `null` if none exists.
     */
    public function getRootModuleName()
    {
        return $this->rootModule ? $this->rootModule->getName() : null;
    }

    /**
     * Returns all installed modules.
     *
     * The installed modules are all modules that are not the root module.
     *
     * @return Module[] The installed modules indexed by their names.
     */
    public function getInstalledModules()
    {
        $modules = $this->modules;

        if ($this->rootModule) {
            unset($modules[$this->rootModule->getName()]);
        }

        return $modules;
    }

    /**
     * Returns the names of all installed modules.
     *
     * The installed modules are all modules that are not the root module.
     *
     * @return string[] The names of the installed modules.
     */
    public function getInstalledModuleNames()
    {
        return array_keys($this->getInstalledModules());
    }

    /**
     * Returns the names of all modules.
     *
     * @return string[] The module names.
     */
    public function getModuleNames()
    {
        return array_keys($this->modules);
    }

    /**
     * Returns the modules in the collection.
     *
     * @return Module[] The modules in the collection.
     */
    public function toArray()
    {
        return $this->modules;
    }

    /**
     * Returns whether the collection is empty.
     *
     * @return bool Returns `true` if the collection is empty and `false`
     *              otherwise.
     */
    public function isEmpty()
    {
        return 0 === count($this->modules);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new ArrayIterator($this->modules);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->modules);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($name)
    {
        return $this->contains($name);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($name)
    {
        return $this->get($name);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($name, $module)
    {
        $this->add($module);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($name)
    {
        $this->remove($name);
    }
}
