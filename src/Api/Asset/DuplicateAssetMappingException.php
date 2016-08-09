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
     * Creates an exception for an asset mapping that exists already.
     *
     * @param AssetMapping   $assetMapping The duplicate asset mapping.
     * @param Exception|null $cause        The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function forAssetMapping(AssetMapping $assetMapping, Exception $cause = null)
    {
        return new static(sprintf(
            'The asset mapping with glob "%s", server name "%s" and path "%s" exists already.',
            $assetMapping->getGlob(),
            $assetMapping->getServerName(),
            $assetMapping->getServerPath()
        ), 0, $cause);
    }
}
