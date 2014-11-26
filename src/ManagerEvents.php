<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager;

/**
 * Contains the events triggered by this package.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ManagerEvents
{
    /**
     * Dispatched when a package JSON file was loaded.
     */
    const LOAD_PACKAGE_CONFIG = 'package-config.load';

    /**
     * Dispatched when package JSON data was generated.
     */
    const SAVE_PACKAGE_CONFIG = 'package-config.save';

    private final function __construct()
    {
    }
}
