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
 * The configuration of the package repository.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageRepositoryConfig
{
    /**
     * @var PackageDescriptor[]
     */
    private $packageDescriptors = array();

    /**
     * Returns the package descriptors.
     *
     * @return PackageDescriptor[] The package descriptors.
     */
    public function getPackageDescriptors()
    {
        return $this->packageDescriptors;
    }

    /**
     * Sets the package descriptors.
     *
     * @param PackageDescriptor[] $descriptors The package descriptors.
     */
    public function setPackageDescriptors(array $descriptors)
    {
        $this->packageDescriptors = $descriptors;
    }

    /**
     * Adds a package descriptor.
     *
     * @param PackageDescriptor $descriptor The package descriptor.
     */
    public function addPackageDescriptor(PackageDescriptor $descriptor)
    {
        $this->packageDescriptors[] = $descriptor;
    }
}
