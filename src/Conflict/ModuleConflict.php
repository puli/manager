<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Conflict;

/**
 * A conflict between two modules claiming the same token.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see    ModuleConflictDetector
 */
class ModuleConflict
{
    /**
     * @var string
     */
    private $conflictingToken;

    /**
     * @var string[]
     */
    private $moduleNames;

    /**
     * Creates the conflict.
     *
     * @param string   $conflictingToken The token that caused the conflict.
     * @param string[] $moduleNames      The names of the modules claiming the
     *                                   token.
     */
    public function __construct($conflictingToken, array $moduleNames)
    {
        sort($moduleNames);

        $this->conflictingToken = $conflictingToken;
        $this->moduleNames = array();

        foreach ($moduleNames as $moduleName) {
            $this->moduleNames[$moduleName] = true;
        }
    }

    /**
     * Returns the conflicting repository path.
     *
     * @return string The conflicting repository path.
     */
    public function getConflictingToken()
    {
        return $this->conflictingToken;
    }

    /**
     * Returns the names of the modules causing the conflict.
     *
     * @return string[] The name of the first conflicting module.
     */
    public function getModuleNames()
    {
        return array_keys($this->moduleNames);
    }

    /**
     * Returns whether the conflict involves a given module name.
     *
     * @param string $moduleName A module name.
     *
     * @return bool Returns `true` if the module caused the conflict.
     */
    public function involvesModule($moduleName)
    {
        return isset($this->moduleNames[$moduleName]);
    }

    /**
     * Returns the opposing module names in the conflict.
     *
     * @param string $moduleName The name of a module.
     *
     * @return string[] Returns the names of the opposing modules or an empty
     *                  array if the module is not involved in the conflict.
     */
    public function getOpponents($moduleName)
    {
        if (!isset($this->moduleNames[$moduleName])) {
            return array();
        }

        $opponents = $this->moduleNames;

        unset($opponents[$moduleName]);

        return array_keys($opponents);
    }
}
