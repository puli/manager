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
 * Contains constants representing the state of a binding type.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class BindingTypeState
{
    /**
     * State: The type is enabled.
     */
    const ENABLED = 1;

    /**
     * State: The binding is disabled because it was defined twice or more.
     */
    const DUPLICATE = 2;

    /**
     * Returns all states.
     *
     * @return int[] The states.
     */
    public static function all()
    {
        return array(
            self::ENABLED,
            self::DUPLICATE
        );
    }

    /**
     * Must not be instantiated.
     */
    private function __construct() {}
}
