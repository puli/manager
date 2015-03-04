<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Discovery;

use Puli\Discovery\Api\DuplicateTypeException;
use Puli\RepositoryManager\Api\Environment\ProjectEnvironment;
use Rhumsaa\Uuid\Uuid;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface DiscoveryManager
{
    /**
     * Returns the manager's environment.
     *
     * @return ProjectEnvironment The project environment.
     */
    public function getEnvironment();

    /**
     * Adds a new binding type.
     *
     * @param BindingTypeDescriptor $typeDescriptor The type to add.
     *
     * @throws DuplicateTypeException If the type is already defined.
     */
    public function addBindingType(BindingTypeDescriptor $typeDescriptor);

    /**
     * Removes a binding type.
     *
     * @param string $typeName The name of the type to remove.
     */
    public function removeBindingType($typeName);

    /**
     * Returns all binding types.
     *
     * You can optionally filter types by one or multiple package names.
     *
     * @param string|string[] $packageName The package name(s) to filter by.
     * @param int             $state       The state of the types to return.
     *
     * @return BindingTypeDescriptor[] The binding types.
     */
    public function getBindingTypes($packageName = null, $state = null);

    /**
     * Adds a new binding.
     *
     * @param BindingDescriptor $bindingDescriptor The binding to add.
     */
    public function addBinding(BindingDescriptor $bindingDescriptor);

    /**
     * Removes a binding.
     *
     * @param Uuid $uuid The UUID of the binding.
     */
    public function removeBinding(Uuid $uuid);

    /**
     * Enables a binding.
     *
     * @param Uuid            $uuid        The UUID of the binding.
     * @param string|string[] $packageName The package name to enable the
     *                                     binding in. Useful if the same
     *                                     binding exists in multiple packages.
     *
     * @throws NoSuchBindingException If the binding could not be found.
     * @throws CannotEnableBindingException If the binding could not be enabled.
     */
    public function enableBinding(Uuid $uuid, $packageName = null);

    /**
     * Disables a binding.
     *
     * @param Uuid            $uuid        The UUID of the binding.
     * @param string|string[] $packageName The package name to disable the
     *                                     binding in. Useful if the same
     *                                     binding exists in multiple packages.
     *
     * @throws NoSuchBindingException If the binding could not be found.
     * @throws CannotDisableBindingException If the binding could not be disabled.
     */
    public function disableBinding(Uuid $uuid, $packageName = null);

    /**
     * Returns all bindings.
     *
     * @return BindingDescriptor[] The bindings.
     */
    public function getBindings();

    /**
     * Returns all bindings matching the given criteria.
     *
     * @param BindingCriteria $criteria The search criteria.
     *
     * @return BindingDescriptor[] The bindings matching the criteria.
     */
    public function findBindings(BindingCriteria $criteria);

    /**
     * Builds the resource discovery.
     */
    public function buildDiscovery();

    /**
     * Clears all contents of the resource discovery.
     */
    public function clearDiscovery();
}
