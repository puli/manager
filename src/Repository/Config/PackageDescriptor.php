<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Repository\Config;

/**
 * Describes a package in the repository configuration.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageDescriptor
{
    /**
     * @var string
     */
    private $installPath;

    /**
     * @var bool
     */
    private $new;

    /**
     * Creates a new package descriptor.
     *
     * @param string $installPath The path where the package is installed.
     *                            If a relative path is given, the path is
     *                            assumed to be relative to the install path
     *                            of the root package.
     * @param bool   $new         Whether the package is new. Optional.
     *
     * @throws \InvalidArgumentException If any of the arguments is invalid.
     */
    public function __construct($installPath, $new = true)
    {
        if (!is_string($installPath)) {
            throw new \InvalidArgumentException(sprintf(
                'The passed install path must be a string. Got: %s',
                is_object($installPath) ? get_class($installPath) : gettype($installPath)
            ));
        }

        if ('' === $installPath) {
            throw new \InvalidArgumentException('The passed install path must not be empty.');
        }

        if (!is_bool($new)) {
            throw new \InvalidArgumentException(sprintf(
                'The parameter $new must be a bool. Got: %s',
                is_object($new) ? get_class($new) : gettype($new)
            ));
        }

        $this->installPath = $installPath;
        $this->new = $new;
    }

    /**
     * Returns the path where the package is installed.
     *
     * @return string The path where the package is installed. The path is
     *                either absolute or relative to the install path of the
     *                root package.
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
