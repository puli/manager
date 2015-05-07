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
     * Dispatched before the resource repository is built.
     */
    const POST_BUILD_REPOSITORY = 'puli.post-build-repository';
}
