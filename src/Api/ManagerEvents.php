<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api;

/**
 * Contains the events triggered by this package.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ManagerEvents
{
    /**
     * Dispatched when a package file is loaded.
     */
    const LOAD_PACKAGE_FILE = 'package-file.load';

    /**
     * Dispatched when a package file is saved.
     */
    const SAVE_PACKAGE_FILE = 'package-file.save';
}
