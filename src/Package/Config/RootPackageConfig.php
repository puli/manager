<?php

/*
 * This file is part of the Puli Packages package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Packages\Package\Config;

/**
 * The configuration of the root Puli package.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageConfig extends PackageConfig
{
    /**
     * @var string[]
     */
    private $packageOrder = array();

    /**
     * @var string|null
     */
    private $repositoryConfig;

    /**
     * Returns the order in which some packages should be loaded.
     *
     * If packages contain conflicting resource definitions, this setting can be
     * used to specify in which order these packages should be loaded.
     *
     * @return string[] A list of package names.
     */
    public function getPackageOrder()
    {
        return $this->packageOrder;
    }

    /**
     * Sets the order in which some packages should be loaded.
     *
     * If packages contain conflicting resource definitions, this setting can be
     * used to specify in which order these packages should be loaded.
     *
     * @param string[] $packageOrder A list of package names.
     */
    public function setPackageOrder($packageOrder)
    {
        $this->packageOrder = $packageOrder;
    }

    /**
     * Returns the path to the repository configuration file.
     *
     * The path is relative to the install path of the root package.
     *
     * @return null|string The path or `null` if none is set.
     */
    public function getRepositoryConfig()
    {
        return $this->repositoryConfig;
    }

    /**
     * Sets the path to the repository configuration file.
     *
     * The path should be relative to the install path of the root package.
     *
     * @param string $repositoryConfig The path to the configuration file.
     */
    public function setRepositoryConfig($repositoryConfig)
    {
        $this->repositoryConfig = $repositoryConfig;
    }


}
