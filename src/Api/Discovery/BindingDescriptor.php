<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Discovery;

use Exception;
use Puli\Discovery\Api\Binding\Binding;
use Puli\Discovery\Binding\ResourceBinding;
use Puli\Manager\Api\AlreadyLoadedException;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\NotLoadedException;
use Rhumsaa\Uuid\Uuid;

/**
 * Describes a resource binding.
 *
 * This class contains a high-level model of {@link ResourceBinding} as it is
 * used in this module.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see    ResourceBinding
 */
class BindingDescriptor
{
    /**
     * @var Binding
     */
    private $binding;

    /**
     * @var int
     */
    private $state;

    /**
     * @var Module
     */
    private $containingModule;

    /**
     * @var BindingTypeDescriptor
     */
    private $typeDescriptor;

    /**
     * @var Exception[]
     */
    private $loadErrors;

    /**
     * Creates a new binding descriptor.
     *
     * @param Binding $binding The described binding.
     */
    public function __construct(Binding $binding)
    {
        $this->binding = $binding;
    }

    /**
     * Loads the binding descriptor.
     *
     * @param Module                     $containingModule The module that
     *                                                     contains the
     *                                                     descriptor.
     * @param BindingTypeDescriptor|null $typeDescriptor   The type descriptor.
     *
     * @throws AlreadyLoadedException If the descriptor is already loaded.
     */
    public function load(Module $containingModule, BindingTypeDescriptor $typeDescriptor = null)
    {
        if (null !== $this->state) {
            throw new AlreadyLoadedException('The binding descriptor is already loaded.');
        }

        $this->loadErrors = array();

        if ($typeDescriptor && $typeDescriptor->isLoaded() && $typeDescriptor->isEnabled()) {
            try {
                $this->binding->initialize($typeDescriptor->getType());
            } catch (Exception $e) {
                $this->loadErrors[] = $e;
            }
        }

        $this->containingModule = $containingModule;
        $this->typeDescriptor = $typeDescriptor;

        $this->refreshState();
    }

    /**
     * Unloads the binding descriptor.
     *
     * All memory allocated during {@link load()} is freed.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     */
    public function unload()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        $this->containingModule = null;
        $this->typeDescriptor = null;
        $this->loadErrors = array();
        $this->state = null;
    }

    /**
     * Returns whether the descriptor is loaded.
     *
     * @return bool Returns `true` if the descriptor is loaded.
     */
    public function isLoaded()
    {
        return null !== $this->state;
    }

    /**
     * Returns the name of the bound type.
     *
     * @return string The type name.
     */
    public function getTypeName()
    {
        return $this->binding->getTypeName();
    }

    /**
     * Returns the described binding.
     *
     * @return Binding The binding.
     */
    public function getBinding()
    {
        return $this->binding;
    }

    /**
     * Returns the errors that happened during loading.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return Exception[] The load errors.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     */
    public function getLoadErrors()
    {
        if (null === $this->loadErrors) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return $this->loadErrors;
    }

    /**
     * Returns the module that contains the descriptor.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return Module The containing module.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     */
    public function getContainingModule()
    {
        if (null === $this->containingModule) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return $this->containingModule;
    }

    /**
     * Returns the type descriptor.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return BindingTypeDescriptor|null The type descriptor or null, if no
     *                                    type descriptor exists for the
     *                                    binding's type name.
     *
     * @throws NotLoadedException If the binding descriptor is not loaded.
     */
    public function getTypeDescriptor()
    {
        // Check containing module, as the type descriptor may be null
        if (null === $this->containingModule) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return $this->typeDescriptor;
    }

    /**
     * Returns the state of the binding.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return int One of the {@link BindingState} constants.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     */
    public function getState()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return $this->state;
    }

    /**
     * Returns whether the binding is enabled.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return bool Returns `true` if the state is {@link BindingState::ENABLED}.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     *
     * @see BindingState::ENABLED
     */
    public function isEnabled()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return BindingState::ENABLED === $this->state;
    }

    /**
     * Returns whether the type of the binding does not exist.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return bool Returns `true` if the state is {@link BindingState::TYPE_NOT_FOUND}.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     *
     * @see BindingState::TYPE_NOT_FOUND
     */
    public function isTypeNotFound()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return BindingState::TYPE_NOT_FOUND === $this->state;
    }

    /**
     * Returns whether the type of the binding is not enabled.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return bool Returns `true` if the state is {@link BindingState::TYPE_NOT_ENABLED}.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     *
     * @see BindingState::TYPE_NOT_ENABLED
     */
    public function isTypeNotEnabled()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return BindingState::TYPE_NOT_ENABLED === $this->state;
    }

    /**
     * Returns whether the binding is invalid.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return bool Returns `true` if the state is {@link BindingState::INVALID}.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     *
     * @see BindingState::INVALID
     */
    public function isInvalid()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return BindingState::INVALID === $this->state;
    }

    private function refreshState()
    {
        if (null === $this->typeDescriptor || !$this->typeDescriptor->isLoaded()) {
            $this->state = BindingState::TYPE_NOT_FOUND;
        } elseif (!$this->typeDescriptor->isEnabled()) {
            $this->state = BindingState::TYPE_NOT_ENABLED;
        } elseif (count($this->loadErrors) > 0) {
            $this->state = BindingState::INVALID;
        } elseif ($this->containingModule instanceof RootModule) {
            $this->state = BindingState::ENABLED;
        } else {
            $this->state = BindingState::ENABLED;
        }
    }
}
