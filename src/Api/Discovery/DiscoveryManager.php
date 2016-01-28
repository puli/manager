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

use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\NonRootModuleExpectedException;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Expression\Expression;

/**
 * Manages the resource discovery of a Puli project.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface DiscoveryManager
{
    /**
     * Flag: Don't check whether the type exists already in
     * {@link addRootTypeDescriptor()}.
     */
    const OVERRIDE = 1;

    /**
     * Flag: Ignore if the type is not found in {@link addBinding()}.
     */
    const IGNORE_TYPE_NOT_FOUND = 2;

    /**
     * Flag: Ignore if the type is not enabled in {@link addBinding()}.
     */
    const IGNORE_TYPE_NOT_ENABLED = 4;

    /**
     * Returns the manager's context.
     *
     * @return ProjectContext The project context.
     */
    public function getContext();

    /**
     * Adds a new binding type.
     *
     * The type definition is added to the root module file.
     *
     * @param BindingTypeDescriptor $typeDescriptor The type to add.
     * @param int                   $flags          A bitwise combination of the
     *                                              flag constants in this class.
     *
     * @throws DuplicateTypeException If the type is already defined.
     */
    public function addRootTypeDescriptor(BindingTypeDescriptor $typeDescriptor, $flags = 0);

    /**
     * Removes a binding type from the root module.
     *
     * The type definition is removed from the root module file. If the type
     * is not found, this method does nothing.
     *
     * @param string $typeName The name of the type to remove.
     */
    public function removeRootTypeDescriptor($typeName);

    /**
     * Removes all binding types matching the given expression.
     *
     * If no matching binding types are found, this method does nothing.
     *
     * @param Expression $expr The search criteria.
     */
    public function removeRootTypeDescriptors(Expression $expr);

    /**
     * Removes all binding types from the root module.
     */
    public function clearRootTypeDescriptors();

    /**
     * Returns the binding type with the given name from the root module.
     *
     * @param string $typeName The name of the type.
     *
     * @return BindingTypeDescriptor The binding type.
     *
     * @throws NoSuchTypeException If the type does not exist.
     */
    public function getRootTypeDescriptor($typeName);

    /**
     * Returns all binding types from the root module.
     *
     * @return BindingTypeDescriptor[] The binding types.
     */
    public function getRootTypeDescriptors();

    /**
     * Returns all binding types from the root module that match the given
     * expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return BindingTypeDescriptor[] The binding types matching the expression.
     */
    public function findRootTypeDescriptors(Expression $expr);

    /**
     * Returns whether the type with the given name exists in the root module.
     *
     * @param string $typeName The name of the type.
     *
     * @return bool Returns `true` if the type exists and `false` otherwise.
     */
    public function hasRootTypeDescriptor($typeName);

    /**
     * Returns whether the manager has any binding types in the root module.
     *
     * You can optionally pass an expression to check whether the manager has
     * types matching the expression.
     *
     * @param Expression|null $expr The search criteria.
     *
     * @return bool Returns `true` if the manager has binding types in the root
     *              module and `false` otherwise. If an expression was passed,
     *              this method only returns `true` if the manager has binding
     *              types matching the expression.
     */
    public function hasRootTypeDescriptors(Expression $expr = null);

    /**
     * Returns the binding type with the given name.
     *
     * @param string $typeName   The name of the type.
     * @param string $moduleName The name of the module to check.
     *
     * @return BindingTypeDescriptor The binding type.
     *
     * @throws NoSuchTypeException If the type does not exist.
     */
    public function getTypeDescriptor($typeName, $moduleName);

    /**
     * Returns all binding types.
     *
     * @return BindingTypeDescriptor[] The binding types.
     */
    public function getTypeDescriptors();

    /**
     * Returns all binding types matching the given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return BindingTypeDescriptor[] The binding types matching the expression.
     */
    public function findTypeDescriptors(Expression $expr);

    /**
     * Returns whether the type with the given name exists.
     *
     * @param string      $typeName   The name of the type.
     * @param string|null $moduleName The name of the module to check. Useful
     *                                if types with the same name exist in
     *                                multiple modules.
     *
     * @return bool Returns `true` if the type exists and `false` otherwise.
     */
    public function hasTypeDescriptor($typeName, $moduleName = null);

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
    public function hasTypeDescriptors(Expression $expr = null);

    /**
     * Adds a new binding.
     *
     * The binding descriptor is added to the root module file.
     *
     * @param BindingDescriptor $bindingDescriptor The binding to add.
     * @param int               $flags             A bitwise combination of the
     *                                             flag constants in this class.
     *
     * @throws NoSuchTypeException       If the type referenced by the descriptor does
     *                                   not exist.
     * @throws TypeNotEnabledException   If the type referenced by the descriptor
     *                                   is not enabled.
     * @throws DuplicateBindingException If a binding with the same UUID exists
     *                                   already.
     */
    public function addRootBindingDescriptor(BindingDescriptor $bindingDescriptor, $flags = 0);

    /**
     * Removes a binding from the root module.
     *
     * The binding descriptor is removed from the root module file. If the
     * binding is not found, this method does nothing.
     *
     * @param Uuid $uuid The UUID of the binding.
     */
    public function removeRootBindingDescriptor(Uuid $uuid);

    /**
     * Removes all bindings matching the given expression.
     *
     * If no matching bindings are found, this method does nothing.
     *
     * @param Expression $expr The search criteria.
     */
    public function removeRootBindingDescriptors(Expression $expr);

    /**
     * Removes all bindings from the root module.
     *
     * If no bindings are found, this method does nothing.
     */
    public function clearRootBindingDescriptors();

    /**
     * Returns the binding with the given UUID in the root module.
     *
     * @param Uuid $uuid The UUID of the binding.
     *
     * @return BindingDescriptor The binding.
     *
     * @throws NoSuchBindingException If the binding does not exist.
     */
    public function getRootBindingDescriptor(Uuid $uuid);

    /**
     * Returns all bindings in the root module.
     *
     * @return BindingDescriptor[] The bindings.
     */
    public function getRootBindingDescriptors();

    /**
     * Returns all bindings from the root module that match the given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return BindingDescriptor[] The bindings matching the expression.
     */
    public function findRootBindingDescriptors(Expression $expr);

    /**
     * Returns whether the binding with the given UUID exists in the root
     * module.
     *
     * @param Uuid $uuid The UUID of the binding.
     *
     * @return bool Returns `true` if the binding exists in the root module and
     *              `false` otherwise.
     */
    public function hasRootBindingDescriptor(Uuid $uuid);

    /**
     * Returns whether the manager has any bindings in the root module.
     *
     * You can optionally pass an expression to check whether the manager has
     * bindings matching the expression.
     *
     * @param Expression|null $expr The search criteria.
     *
     * @return bool Returns `true` if the manager has bindings in the root
     *              module and `false` otherwise. If an expression was passed,
     *              this method only returns `true` if the manager has bindings
     *              matching the expression.
     */
    public function hasRootBindingDescriptors(Expression $expr = null);

    /**
     * Enables a binding.
     *
     * @param Uuid $uuid The UUID of the binding.
     *
     * @throws NoSuchBindingException         If the binding does not exist.
     * @throws NoSuchTypeException            If the type referenced by the descriptor does
     *                                        not exist.
     * @throws TypeNotEnabledException        If the type referenced by the descriptor
     *                                        is not enabled.
     * @throws NonRootModuleExpectedException If the binding is in the root
     *                                        module. Can only enable bindings
     *                                        in non-root modules, because the
     *                                        bindings in the root module are
     *                                        implicitly enabled.
     */
    public function enableBindingDescriptor(Uuid $uuid);

    /**
     * Disables a binding.
     *
     * @param Uuid $uuid The UUID of the binding.
     *
     * @throws NoSuchBindingException         If the binding does not exist.
     * @throws NoSuchTypeException            If the type referenced by the descriptor does
     *                                        not exist.
     * @throws TypeNotEnabledException        If the type referenced by the descriptor
     *                                        is not enabled.
     * @throws NonRootModuleExpectedException If the binding is in the root
     *                                        module. Can only disable bindings
     *                                        in non-root modules, because the
     *                                        bindings in the root module are
     *                                        implicitly enabled.
     */
    public function disableBindingDescriptor(Uuid $uuid);

    /**
     * Removes disabled binding UUIDs that were not found in any module.
     */
    public function removeObsoleteDisabledBindingDescriptors();

    /**
     * Returns the binding with the given UUID.
     *
     * @param Uuid $uuid The UUID of the binding.
     *
     * @return BindingDescriptor The binding.
     *
     * @throws NoSuchBindingException If the binding does not exist.
     */
    public function getBindingDescriptor(Uuid $uuid);

    /**
     * Returns all bindings.
     *
     * @return BindingDescriptor[] The bindings.
     */
    public function getBindingDescriptors();

    /**
     * Returns all bindings matching the given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return BindingDescriptor[] The bindings matching the expression.
     */
    public function findBindingDescriptors(Expression $expr);

    /**
     * Returns whether the binding with the given UUID exists.
     *
     * @param Uuid $uuid The UUID of the binding.
     *
     * @return bool Returns `true` if the binding exists and `false` otherwise.
     */
    public function hasBindingDescriptor(Uuid $uuid);

    /**
     * Returns whether the manager has any bindings.
     *
     * You can optionally pass an expression to check whether the manager has
     * bindings matching the expression.
     *
     * @param Expression|null $expr The search criteria.
     *
     * @return bool Returns `true` if the manager has bindings and `false`
     *              otherwise. If an expression was passed, this method only
     *              returns `true` if the manager has bindings matching the
     *              expression.
     */
    public function hasBindingDescriptors(Expression $expr = null);

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
