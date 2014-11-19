<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Event;

/**
 * Contains the events triggered by the package manager.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageEvents
{
    /**
     * Thrown when a package JSON file was loaded.
     */
    const PACKAGE_JSON_LOADED = 'package-json-loaded';

    private final function __construct()
    {
    }
}
