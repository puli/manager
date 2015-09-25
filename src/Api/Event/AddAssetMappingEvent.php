<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Event;

use Puli\Manager\Api\Asset\AssetMapping;
use Symfony\Component\EventDispatcher\Event;

/**
 * Dispatched when an asset mapping is added.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AddAssetMappingEvent extends Event
{
    /**
     * @var AssetMapping
     */
    private $mapping;

    /**
     * Creates the event.
     *
     * @param AssetMapping $mapping The asset mapping.
     */
    public function __construct(AssetMapping $mapping)
    {
        $this->mapping = $mapping;
    }

    /**
     * Returns the added asset mapping.
     *
     * @return AssetMapping The asset mapping.
     */
    public function getAssetMapping()
    {
        return $this->mapping;
    }
}
