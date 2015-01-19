<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Repository;

use Puli\RepositoryManager\Conflict\PackageConflictException;
use Puli\RepositoryManager\FileNotFoundException;

/**
 * Contains constants representing the state of a resource mapping.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class ResourceMappingState
{
    /**
     * State: The mapping is enabled.
     */
    const ENABLED = 1;

    /**
     * State: The path referenced by the mapping was not found.
     */
    const NOT_FOUND = 2;

    /**
     * State: The mapping conflicts with a mapping in another package.
     */
    const CONFLICT = 3;

    /**
     * Returns all mapping states.
     *
     * @return int[] The mapping states.
     */
    public static function all()
    {
        return array(
            self::ENABLED,
            self::NOT_FOUND,
            self::CONFLICT,
        );
    }

    /**
     * Must not be instantiated.
     */
    private function __construct() {}
}
