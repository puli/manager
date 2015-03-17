<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Event;

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
}
