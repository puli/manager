<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package;

use Puli\RepositoryManager\Package\Config\RootPackageConfig;

/**
 * The root package.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackage extends Package
{
    /**
     * Creates a new root package.
     *
     * @param RootPackageConfig $config      The package configuration.
     * @param string            $installPath The install path of the package.
     */
    public function __construct(RootPackageConfig $config, $installPath)
    {
        parent::__construct($config, $installPath);

        if (null === $this->getName()) {
            $this->setName('__root__');
        }
    }

    /**
     * Returns the configuration of the package.
     *
     * @return RootPackageConfig The package configuration.
     */
    public function getConfig()
    {
        return parent::getConfig();
    }
}
