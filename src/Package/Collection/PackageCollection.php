<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Package\Collection;

use Puli\PackageManager\Package\Package;
use Puli\PackageManager\Package\RootPackage;

/**
 * A collection of Puli packages.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageCollection implements \IteratorAggregate, \Countable, \ArrayAccess
{
    /**
     * @var RootPackage
     */
    private $rootPackage;

    /**
     * @var Package[]
     */
    private $packages = array();

    public function __construct(array $packages = array())
    {
        foreach ($packages as $package) {
            $this->add($package);
        }
    }

    /**
     * Adds a package to the collection.
     *
     * @param Package $package The added package.
     */
    public function add(Package $package)
    {
        $this->packages[$package->getName()] = $package;

        if ($package instanceof RootPackage) {
            $this->rootPackage = $package;
        }
    }

    /**
     * Removes a package from the collection.
     *
     * @param string $name The package name.
     */
    public function remove($name)
    {
        if ($this->rootPackage && $name === $this->rootPackage->getName()) {
            $this->rootPackage = null;
        }

        unset($this->packages[$name]);
    }

    /**
     * Returns the package with the given name.
     *
     * @param string $name The package name.
     *
     * @return Package The package with the passed name.
     *
     * @throws NoSuchPackageException If the package was not found.
     */
    public function get($name)
    {
        if (!isset($this->packages[$name])) {
            throw new NoSuchPackageException(sprintf(
                'The package "%s" was not found.',
                $name
            ));
        }

        return $this->packages[$name];
    }

    /**
     * Returns whether a package with the given name exists.
     *
     * @param string $name The package name.
     *
     * @return bool Whether a package with this name exists.
     */
    public function contains($name)
    {
        return isset($this->packages[$name]);
    }

    /**
     * Returns the root package.
     *
     * If the collection contains no root package, `null` is returned.
     *
     * @return RootPackage|null The root package or `null` if none exists.
     */
    public function getRootPackage()
    {
        return $this->rootPackage;
    }

    /**
     * Returns the packages in the collection.
     *
     * @return Package[] The packages in the collection.
     */
    public function toArray()
    {
        return $this->packages;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->packages);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->packages);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($name)
    {
        return $this->contains($name);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($name)
    {
        return $this->get($name);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($name, $package)
    {
        $this->add($package);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($name)
    {
        $this->remove($name);
    }
}
