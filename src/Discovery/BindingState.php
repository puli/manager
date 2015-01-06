<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Discovery;

/**
 * Contains constants representing the state of a binding.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class BindingState
{
    /**
     * State: The binding is not loaded.
     */
    const UNLOADED = 0;

    /**
     * State: The binding is enabled.
     */
    const ENABLED = 1;

    /**
     * State: The binding is disabled.
     */
    const DISABLED = 2;

    /**
     * State: The binding is neither enabled nor disabled.
     */
    const UNDECIDED = 3;

    /**
     * State: The binding is held back. The referenced type is not loaded.
     */
    const HELD_BACK = 4;

    /**
     * State: The binding is ignored. This happens if its type is duplicated.
     */
    const IGNORED = 5;

    /**
     * Returns all binding states.
     *
     * @return int[] The binding states.
     */
    public static function all()
    {
        return array(
            self::UNLOADED,
            self::ENABLED,
            self::DISABLED,
            self::UNDECIDED,
            self::HELD_BACK,
            self::IGNORED
        );
    }

    /**
     * Must not be instantiated.
     */
    private function __construct() {}
}
