<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Discovery;

/**
 * Contains constants representing the state of a binding.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class BindingState
{
    /**
     * State: The binding is enabled.
     */
    const ENABLED = 1;

    /**
     * State: The binding's type does not exist.
     */
    const TYPE_NOT_FOUND = 2;

    /**
     * State: The binding's type does not exist.
     */
    const TYPE_NOT_ENABLED = 3;

    /**
     * State: The binding does not match the constraints of the binding type.
     */
    const INVALID = 4;

    /**
     * Returns all binding states.
     *
     * @return int[] The binding states.
     */
    public static function all()
    {
        return array(
            self::ENABLED,
            self::TYPE_NOT_FOUND,
            self::TYPE_NOT_ENABLED,
            self::INVALID,
        );
    }

    /**
     * Must not be instantiated.
     */
    private function __construct()
    {
    }
}
