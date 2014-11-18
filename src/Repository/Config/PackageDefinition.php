<?php

/*
 * This file is part of the Puli Packages package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Packages\Repository\Config;

/**
 * A package in the package repository configuration.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageDefinition
{
    /**
     * @var string
     */
    private $installPath;

    /**
     * @var bool
     */
    private $new = true;

    /**
     * Creates a new package definition.
     *
     * @param string $installPath The path where the package is installed.
     */
    public function __construct($installPath)
    {
        $this->installPath = $installPath;
    }

    /**
     * Returns the path where the package is installed.
     *
     * @return string The path where the package is installed.
     */
    public function getInstallPath()
    {
        return $this->installPath;
    }

    /**
     * Returns whether the package is new.
     *
     * @return bool Whether the package is new.
     */
    public function isNew()
    {
        return $this->new;
    }

    /**
     * Sets whether the package is new.
     *
     * @param bool $new Whether the package is new.
     */
    public function setNew($new)
    {
        $this->new = $new;
    }
}
