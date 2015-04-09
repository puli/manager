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

use Puli\Manager\Api\Environment\ProjectEnvironment;
use Puli\Manager\Api\NonRootPackageExpectedException;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Expression\Expression;

/**
 * Manages the resource discovery of a Puli project.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface DiscoveryManager
{
    /**
     * Flag: Don't check whether the type exists already in
     * {@link addBindingType()}.
     */
    const NO_DUPLICATE_CHECK = 1;

    /**
     * Flag: Don't check whether the referenced type exists/is enabled in
     * {@link addBinding()}.
     */
    const NO_TYPE_CHECK = 2;

    /**
     * Returns the manager's environment.
     *
     * @return ProjectEnvironment The project environment.
     */
    public function getEnvironment();

    /**
     * Adds a new binding type.
     *
     * The type definition is added to the root package file.
     *
     * @param BindingTypeDescriptor $typeDescriptor The type to add.
     * @param int                   $flags          A bitwise combination of the
     *                                              flag constants in this class.
     *
     * @throws DuplicateTypeException If the type is already defined.
     */
    public function addRootBindingType(BindingTypeDescriptor $typeDescriptor, $flags = 0);

    /**
     * Removes a binding type from the root package.
     *
     * The type definition is removed from the root package file. If the type
     * is not found, this method does nothing.
     *
     * @param string $typeName The name of the type to remove.
     */
    public function removeRootBindingType($typeName);

    /**
     * Removes all binding types from the root package.
     */
    public function clearRootBindingTypes();

    /**
     * Returns the binding type with the given name from the root package.
     *
     * @param string $typeName The name of the type.
     *
     * @return BindingTypeDescriptor The binding type.
     *
     * @throws NoSuchTypeException If the type does not exist.
     */
    public function getRootBindingType($typeName);

    /**
     * Returns all binding types from the root package.
     *
     * @return BindingTypeDescriptor[] The binding types.
     */
    public function getRootBindingTypes();

    /**
     * Returns whether the type with the given name exists in the root package.
     *
     * @param string $typeName The name of the type.
     *
     * @return bool Returns `true` if the type exists and `false` otherwise.
     */
    public function hasRootBindingType($typeName);

    /**
     * Returns whether the manager has any binding types in the root package.
     *
     * You can optionally pass an expression to check whether the manager has
     * types matching the expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return bool Returns `true` if the manager has binding types in the root
     *              package and `false` otherwise. If an expression was passed,
     *              this method only returns `true` if the manager has binding
     *              types matching the expression.
     */
    public function hasRootBindingTypes(Expression $expr = null);

    /**
     * Returns the binding type with the given name.
     *
     * @param string $typeName    The name of the type.
     * @param string $packageName The name of the package to check.
     *
     * @return BindingTypeDescriptor The binding type.
     *
     * @throws NoSuchTypeException If the type does not exist.
     */
    public function getBindingType($typeName, $packageName);

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
     * The binding descriptor is added to the root package file.
     *
     * @param BindingDescriptor $bindingDescriptor The binding to add.
     * @param int               $flags             A bitwise combination of the
     *                                             flag constants in this class.
     *
     * @throws NoSuchTypeException If the type referenced by the descriptor does
     *                             not exist.
     * @throws TypeNotEnabledException If the type referenced by the descriptor
     *                                 is not enabled.
     * @throws DuplicateBindingException If a binding with the same UUID exists
     *                                   already.
     */
    public function addRootBinding(BindingDescriptor $bindingDescriptor, $flags = 0);

    /**
     * Removes a binding from the root package.
     *
     * The binding descriptor is removed from the root package file. If the
     * binding is not found, this method does nothing.
     *
     * @param Uuid $uuid The UUID of the binding.
     */
    public function removeRootBinding(Uuid $uuid);

    /**
     * Removes all bindings from the root package.
     *
     * If no bindings are found, this method does nothing.
     */
    public function clearRootBindings();

    /**
     * Returns the binding with the given UUID in the root package.
     *
     * @param Uuid $uuid The UUID of the binding.
     *
     * @return BindingDescriptor The binding.
     *
     * @throws NoSuchBindingException If the binding does not exist.
     */
    public function getRootBinding(Uuid $uuid);

    /**
     * Returns all bindings in the root package.
     *
     * @return BindingDescriptor[] The bindings.
     */
    public function getRootBindings();

    /**
     * Returns whether the binding with the given UUID exists in the root
     * package.
     *
     * @param Uuid $uuid The UUID of the binding.
     *
     * @return bool Returns `true` if the binding exists in the root package and
     *              `false` otherwise.
     */
    public function hasRootBinding(Uuid $uuid);

    /**
     * Returns whether the manager has any bindings in the root package.
     *
     * You can optionally pass an expression to check whether the manager has
     * bindings matching the expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return bool Returns `true` if the manager has bindings in the root
     *              package and `false` otherwise. If an expression was passed,
     *              this method only returns `true` if the manager has bindings
     *              matching the expression.
     */
    public function hasRootBindings(Expression $expr = null);

    /**
     * Enables a binding.
     *
     * @param Uuid $uuid The UUID of the binding.
     *
     * @throws NoSuchBindingException If the binding does not exist.
     * @throws NoSuchTypeException If the type referenced by the descriptor does
     *                             not exist.
     * @throws TypeNotEnabledException If the type referenced by the descriptor
     *                                 is not enabled.
     * @throws NonRootPackageExpectedException If the binding is in the root
     *                                         package. Can only enable bindings
     *                                         in non-root packages, because the
     *                                         bindings in the root package are
     *                                         implicitly enabled.
     */
    public function enableBinding(Uuid $uuid);

    /**
     * Disables a binding.
     *
     * @param Uuid $uuid The UUID of the binding.
     *
     * @throws NoSuchBindingException If the binding does not exist.
     * @throws NoSuchTypeException If the type referenced by the descriptor does
     *                             not exist.
     * @throws TypeNotEnabledException If the type referenced by the descriptor
     *                                 is not enabled.
     * @throws NonRootPackageExpectedException If the binding is in the root
     *                                         package. Can only disable bindings
     *                                         in non-root packages, because the
     *                                         bindings in the root package are
     *                                         implicitly enabled.
     */
    public function disableBinding(Uuid $uuid);

    /**
     * Returns the binding with the given UUID.
     *
     * @param Uuid $uuid The UUID of the binding.
     *
     * @return BindingDescriptor The binding.
     *
     * @throws NoSuchBindingException If the binding does not exist.
     */
    public function getBinding(Uuid $uuid);

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
     * @param Uuid $uuid The UUID of the binding.
     *
     * @return bool Returns `true` if the binding exists and `false` otherwise.
     */
    public function hasBinding(Uuid $uuid);

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
     *
     * @throws DiscoveryNotEmptyException If the discovery is not empty.
     */
    public function buildDiscovery();

    /**
     * Clears all contents of the resource discovery.
     */
    public function clearDiscovery();
}
