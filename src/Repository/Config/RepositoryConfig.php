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
 * The configuration of the package repository.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryConfig
{
    /**
     * @var PackageDefinition[]
     */
    private $packageDefinitions = array();

    /**
     * Returns the package definitions.
     *
     * @return PackageDefinition[] The package definitions.
     */
    public function getPackageDefinitions()
    {
        return $this->packageDefinitions;
    }

    /**
     * Sets the package definitions.
     *
     * @param PackageDefinition[] $definitions The package definitions.
     */
    public function setPackageDefinitions(array $definitions)
    {
        $this->packageDefinitions = $definitions;
    }

    /**
     * Adds a package definition.
     *
     * @param PackageDefinition $definition The package definition.
     */
    public function addPackageDefinition(PackageDefinition $definition)
    {
        $this->packageDefinitions[] = $definition;
    }
}
