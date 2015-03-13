<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Discovery\Binding;

use Puli\RepositoryManager\Transaction\OperationInterceptor;
use Rhumsaa\Uuid\Uuid;

/**
 * Updates the overridden marks of all bindings with the given UUID.
 *
 * If more than one enabled binding exists for the given UUID, all but one are
 * marked as overridden.
 *
 * If the UUID is defined in the root package, the binding of the root package
 * is left enabled. Otherwise the enabled binding is chosen randomly.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UpdateOverriddenMarksForUuid implements OperationInterceptor
{
    /**
     * @var Uuid
     */
    private $uuid;

    /**
     * @var BindingDescriptorCollection
     */
    private $bindingDescriptors;

    /**
     * @var string
     */
    private $rootPackageName;

    public function __construct(Uuid $uuid, BindingDescriptorCollection $bindingDescriptors, $rootPackageName)
    {
        $this->uuid = $uuid;
        $this->bindingDescriptors = $bindingDescriptors;
        $this->rootPackageName = $rootPackageName;
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute()
    {
        $this->updateOverriddenMarksForUuid($this->uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function postRollback()
    {
        $this->updateOverriddenMarksForUuid($this->uuid);
    }

    private function updateOverriddenMarksForUuid(Uuid $uuid)
    {
        if (!$this->bindingDescriptors->contains($uuid)) {
            return;
        }

        $bindings = $this->bindingDescriptors->listByUuid($uuid);

        if (1 === count($bindings)) {
            reset($bindings)->markOverridden(false);

            return;
        }

        $oneEnabled = false;

        // Mark all bindings but one as overridden
        // Don't mark root bindings as overridden if possible
        if (isset($bindings[$this->rootPackageName])) {
            // Move root binding to front
            array_unshift($bindings, $bindings[$this->rootPackageName]);
            unset($bindings[$this->rootPackageName]);
        }

        foreach ($bindings as $binding) {
            if (!$oneEnabled && ($binding->isEnabled() || $binding->isOverridden())) {
                $binding->markOverridden(false);
                $oneEnabled = true;
            } else {
                $binding->markOverridden(true);
            }
        }
    }
}
