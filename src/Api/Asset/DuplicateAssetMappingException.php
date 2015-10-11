<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Asset;

use Exception;
use Rhumsaa\Uuid\Uuid;

/**
 * Thrown when an asset mapping exists already.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DuplicateAssetMappingException extends Exception
{
    /**
     * Creates an exception for a UUID that exists already.
     *
     * @param Uuid           $uuid  The UUID of the mapping.
     * @param Exception|null $cause The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function forUuid(Uuid $uuid, Exception $cause = null)
    {
        return new static(sprintf(
            'The asset mapping "%s" exists already.',
            $uuid->toString()
        ), 0, $cause);
    }
}
