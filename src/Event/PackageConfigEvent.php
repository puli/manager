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

use Puli\PackageManager\Package\Config\PackageConfig;
use Symfony\Component\EventDispatcher\Event;

/**
 * Dispatched when a package configuration is read or written.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageConfigEvent extends Event
{
    /**
     * @var PackageConfig
     */
    private $config;

    /**
     * Creates the event.
     *
     * @param PackageConfig $config The package configuration.
     */
    public function __construct(PackageConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Returns the package configuration.
     *
     * @return PackageConfig The package configuration.
     */
    public function getPackageConfig()
    {
        return $this->config;
    }
}
