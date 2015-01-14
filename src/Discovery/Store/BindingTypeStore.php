<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Discovery\Store;

use Puli\RepositoryManager\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Util\CompositeKeyStore;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingTypeStore
{
    /**
     * @var CompositeKeyStore
     */
    private $store;

    public function __construct()
    {
        $this->store = new CompositeKeyStore();
    }

    public function add(BindingTypeDescriptor $type, Package $package)
    {
        $this->store->set($type->getName(), $package->getName(), $type);
    }

    public function get($typeName, Package $package = null)
    {
        if (null !== $package) {
            return $this->store->get($typeName, $package->getName());
        }

        return $this->store->getFirst($typeName);
    }

    /**
     * @param $typeName
     *
     * @return BindingTypeDescriptor[]
     */
    public function getAll($typeName)
    {
        return $this->store->getAll($typeName);
    }

    public function exists($typeName, Package $package)
    {
        return $this->store->contains($typeName, $package->getName());
    }

    public function existsAny($typeName)
    {
        return $this->store->contains($typeName);
    }

    public function existsEnabled($typeName)
    {
        if (!$this->store->contains($typeName)) {
            return false;
        }

        foreach ($this->store->getAll($typeName) as $type) {
            if ($type->isEnabled()) {
                return true;
            }
        }

        return false;
    }

    public function getDefiningPackageNames($typeName)
    {
        return array_keys($this->store->getAll($typeName));
    }

    public function isDuplicate($typeName)
    {
        return $this->store->getCount($typeName) > 1;
    }

    public function getTypeNames()
    {
        return $this->store->getPrimaryKeys();
    }

    public function remove($typeName, Package $package)
    {
        $this->store->remove($typeName, $package->getName());
    }
}
