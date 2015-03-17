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
     * Dispatched when a package file is loaded.
     */
    const LOAD_PACKAGE_FILE = 'puli.load-package-file';

    /**
     * Dispatched when a package file is saved.
     */
    const SAVE_PACKAGE_FILE = 'puli.save-package-file';

    /**
     * Dispatched when the factory class is generated.
     */
    const GENERATE_FACTORY = 'puli.generate-factory';
}
