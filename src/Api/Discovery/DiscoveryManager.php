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

use Puli\Discovery\Api\DuplicateTypeException;
use Puli\Discovery\Api\NoSuchTypeException;
use Puli\Manager\Api\Environment\ProjectEnvironment;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Expression\Expression;

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
     * Returns the binding type with the given name.
     *
     * @param string $typeName    The name of the type.
     * @param string $packageName The name of the package to check. Useful if
     *                            types with the same name exist in multiple
     *                            packages.
     *
     * @return BindingTypeDescriptor The binding type.
     *
     * @throws NoSuchTypeException If the type does not exist.
     */
    public function getBindingType($typeName, $packageName = null);

    /**
     * Returns all binding types.
     *
     * @return BindingTypeDescriptor[] The binding types.
     */
    public function getBindingTypes();

    /**
     * Returns all binding types matching the given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return BindingTypeDescriptor[] The binding types matching the expression.
     */
    public function findBindingTypes(Expression $expr);

    /**
     * Returns whether the type with the given name exists.
     *
     * @param string $typeName    The name of the type.
     * @param string $packageName The name of the package to check. Useful if
     *                            types with the same name exist in multiple
     *                            packages.
     *
     * @return bool Returns `true` if the type exists and `false` otherwise.
     */
    public function hasBindingType($typeName, $packageName = null);

    /**
     * Returns whether the manager has any binding types.
     *
     * You can optionally pass an expression to check whether the manager has
     * types matching the expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return bool Returns `true` if the manager has binding types and `false`
     *              otherwise. If an expression was passed, this method only
     *              returns `true` if the manager has binding types matching the
     *              expression.
     */
    public function hasBindingTypes(Expression $expr = null);

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
     *                                     binding in. Useful if bindings with
     *                                     the same UUID exist in multiple
     *                                     packages.
     *
     * @throws NoSuchBindingException If the binding does not exist.
     * @throws CannotEnableBindingException If the binding could not be enabled.
     */
    public function enableBinding(Uuid $uuid, $packageName = null);

    /**
     * Disables a binding.
     *
     * @param Uuid            $uuid        The UUID of the binding.
     * @param string|string[] $packageName The package name to disable the
     *                                     binding in. Useful if bindings with
     *                                     the same UUID exist in multiple
     *                                     packages.
     *
     * @throws NoSuchBindingException If the binding does not exist.
     * @throws CannotDisableBindingException If the binding could not be disabled.
     */
    public function disableBinding(Uuid $uuid, $packageName = null);

    /**
     * Returns the binding with the given UUID.
     *
     * @param Uuid   $uuid        The UUID of the binding.
     * @param string $packageName The name of the package to check. Useful if
     *                            bindings with the same UUID exist in multiple
     *                            packages.
     *
     * @return BindingDescriptor The binding.
     *
     * @throws NoSuchBindingException If the binding does not exist.
     */
    public function getBinding(Uuid $uuid, $packageName = null);

    /**
     * Returns all bindings.
     *
     * @return BindingDescriptor[] The bindings.
     */
    public function getBindings();

    /**
     * Returns all bindings matching the given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return BindingDescriptor[] The bindings matching the expression.
     */
    public function findBindings(Expression $expr);

    /**
     * Returns whether the binding with the given UUID exists.
     *
     * @param Uuid   $uuid        The UUID of the binding.
     * @param string $packageName The name of the package to check. Useful if
     *                            bindings with the same UUID exist in multiple
     *                            packages.
     *
     * @return bool Returns `true` if the binding exists and `false` otherwise.
     */
    public function hasBinding(Uuid $uuid, $packageName = null);

    /**
     * Returns whether the manager has any bindings.
     *
     * You can optionally pass an expression to check whether the manager has
     * bindings matching the expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return bool Returns `true` if the manager has bindings and `false`
     *              otherwise. If an expression was passed, this method only
     *              returns `true` if the manager has bindings matching the
     *              expression.
     */
    public function hasBindings(Expression $expr = null);

    /**
     * Builds the resource discovery.
     */
    public function buildDiscovery();

    /**
     * Clears all contents of the resource discovery.
     */
    public function clearDiscovery();
}
