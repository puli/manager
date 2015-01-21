<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Discovery;

/**
 * Contains constants representing the state of a binding.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class BindingState
{
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
     * State: The binding does not match the constraints of the binding type.
     */
    const INVALID = 6;

    /**
     * State: The binding is a duplicate of another enabled binding.
     */
    const DUPLICATE = 7;

    /**
     * Returns all binding states.
     *
     * @return int[] The binding states.
     */
    public static function all()
    {
        return array(
            self::ENABLED,
            self::DISABLED,
            self::UNDECIDED,
            self::HELD_BACK,
            self::IGNORED,
            self::INVALID,
        );
    }

    /**
     * Must not be instantiated.
     */
    private function __construct() {}
}
