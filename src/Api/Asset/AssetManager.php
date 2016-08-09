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

use Puli\Manager\Api\Server\NoSuchServerException;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Expression\Expression;

/**
 * Manages asset mappings.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface AssetManager
{
    /**
     * Flag: Override existing asset mappings in {@link addAssetMapping()}.
     */
    const OVERRIDE = 1;

    /**
     * Flag: Ignore if the server does not exist in {@link addAssetMapping()}.
     */
    const IGNORE_SERVER_NOT_FOUND = 2;

    /**
     * Adds an asset mapping to the repository.
     *
     * The mapping is added to the root module.
     *
     * @param AssetMapping $mapping The asset mapping.
     * @param int          $flags   A bitwise combination of the flag constants
     *                              in this class.
     *
     * @throws NoSuchServerException          If the server referred to by the mapping
     *                                        does not exist.
     * @throws DuplicateAssetMappingException If a mapping with the same UUID
     *                                        exists already.
     */
    public function addRootAssetMapping(AssetMapping $mapping, $flags = 0);

    /**
     * Removes all asset mappings matching the given expression.
     *
     * The mappings are removed from the root module. If no matching mappings
     * are found, this method does nothing.
     *
     * @param Expression $expr The search criteria.
     */
    public function removeRootAssetMappings(Expression $expr);

    /**
     * Removes all asset mappings from the repository.
     *
     * The mappings are removed from the root module. If no matching mappings
     * are found, this method does nothing.
     */
    public function clearRootAssetMappings();

    /**
     * Returns all asset mappings in the root module.
     *
     * @return AssetMapping[] The asset mappings.
     */
    public function getRootAssetMappings();

    /**
     * Returns all asset mappings in the root module matching the given
     * expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return AssetMapping[] The asset mappings matching the expression.
     */
    public function findRootAssetMappings(Expression $expr);

    /**
     * Returns whether the root module contains any asset mappings.
     *
     * You can optionally pass an expression to check whether the manager has
     * mappings matching that expression.
     *
     * @param Expression|null $expr The search criteria.
     *
     * @return bool Returns `true` if the root module contains any asset mappings.
     */
    public function hasRootAssetMappings(Expression $expr = null);

    /**
     * Returns all asset mappings.
     *
     * @return AssetMapping[] The asset mappings.
     */
    public function getAssetMappings();

    /**
     * Returns all asset mappings matching the given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return AssetMapping[] The asset mappings matching the expression.
     */
    public function findAssetMappings(Expression $expr);

    /**
     * Returns whether any asset mappings exist.
     *
     * You can optionally pass an expression to check whether the manager has
     * mappings matching that expression.
     *
     * @param Expression|null $expr The search criteria.
     *
     * @return bool Returns `true` if any asset mappings exist.
     */
    public function hasAssetMappings(Expression $expr = null);
}
