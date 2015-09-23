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

/**
 * Contains the events triggered by this package.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface PuliEvents
{
    /**
     * Dispatched when the factory class is generated.
     */
    const GENERATE_FACTORY = 'puli.generate-factory';

    /**
     * Dispatched before the resource repository is built.
     */
    const PRE_BUILD_REPOSITORY = 'puli.pre-build-repository';

    /**
     * Dispatched after the resource repository is built.
     */
    const POST_BUILD_REPOSITORY = 'puli.post-build-repository';

    /**
     * Dispatched after adding asset mappings.
     */
    const POST_ADD_ASSET_MAPPING = 'puli.post-add-asset-mapping';

    /**
     * Dispatched after removing asset mappings.
     */
    const POST_REMOVE_ASSET_MAPPING = 'puli.post-remove-asset-mapping';
}
