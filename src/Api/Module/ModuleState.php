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

/**
 * Contains constants representing the state of a module.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class ModuleState
{
    /**
     * State: The module is enabled.
     */
    const ENABLED = 1;

    /**
     * State: The module was not found.
     */
    const NOT_FOUND = 2;

    /**
     * State: The module file was not loadable.
     */
    const NOT_LOADABLE = 3;

    /**
     * Returns all states.
     *
     * @return int[] The states.
     */
    public static function all()
    {
        return array(
            self::ENABLED,
            self::NOT_FOUND,
            self::NOT_LOADABLE,
        );
    }

    /**
     * Must not be instantiated.
     */
    private function __construct()
    {
    }
}
