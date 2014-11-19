<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Package;

use Puli\PackageManager\Package\Config\PackageConfig;

/**
 * A configured package.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Package
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $installPath;

    /**
     * @var PackageConfig
     */
    private $config;

    /**
     * Creates a new package.
     *
     * @param PackageConfig $config      The package configuration.
     * @param string        $installPath The install path of the package.
     */
    public function __construct(PackageConfig $config, $installPath)
    {
        $this->name = $config->getPackageName();
        $this->config = $config;
        $this->installPath = $installPath;
    }

    /**
     * Returns the name of the package.
     *
     * @return string The name of the package.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the path at which the package is installed.
     *
     * @return string The install path of the package.
     */
    public function getInstallPath()
    {
        return $this->installPath;
    }

    /**
     * Returns the configuration of the package.
     *
     * @return PackageConfig The package configuration.
     */
    public function getConfig()
    {
        return $this->config;
    }
}
