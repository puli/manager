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

use Puli\RepositoryManager\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Package\Package;
use Rhumsaa\Uuid\Uuid;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingStore
{
    /**
     * @var CompositeKeyStore
     */
    private $store;

    public function __construct()
    {
        $this->store = new CompositeKeyStore();
    }

    public function add(BindingDescriptor $binding, Package $package)
    {
        $this->store->set($binding->getUuid()->toString(), $package->getName(), $binding);
    }

    public function get(Uuid $uuid, Package $package = null)
    {
        if (null !== $package) {
            return $this->store->get($uuid->toString(), $package->getName());
        }

        return $this->store->getFirst($uuid->toString());
    }

    public function exists(Uuid $uuid, Package $package)
    {
        return $this->store->contains($uuid->toString(), $package->getName());
    }

    public function existsAny(Uuid $uuid)
    {
        return $this->store->contains($uuid->toString());
    }

    public function existsEnabled(Uuid $uuid)
    {
        $uuidString = $uuid->toString();

        if (!$this->store->contains($uuidString)) {
            return false;
        }

        foreach ($this->store->getAll($uuidString) as $binding) {
            if ($binding->isEnabled()) {
                return true;
            }
        }

        return false;
    }

    public function getDefiningPackageNames(Uuid $uuid)
    {
        return array_keys($this->store->getAll($uuid->toString()));
    }

    public function isDuplicate(Uuid $uuid)
    {
        return $this->store->getCount($uuid->toString()) > 1;
    }

    public function getUuids()
    {
        $uuids = array();

        foreach ($this->store->getKeys() as $key) {
            $uuids[] = $this->store->getFirst($key)->getUuid();
        }

        return $uuids;
    }

    public function remove(Uuid $uuid, Package $package)
    {
        $this->store->remove($uuid->toString(), $package->getName());
    }
}
