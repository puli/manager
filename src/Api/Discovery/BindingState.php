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

use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Api\Package\RootPackage;
use Puli\RepositoryManager\Discovery\BindingTypeDescriptorStore;

/**
 * Contains constants representing the state of a binding.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class BindingState
{
    /**
     * State: The binding is not loaded.
     */
    const NOT_LOADED = 0;

    /**
     * State: The binding is enabled.
     */
    const ENABLED = 1;

    /**
     * State: The binding is disabled.
     */
    const DISABLED = 2;

    /**
     * State: The binding is neither enabled nor disabled.
     */
    const UNDECIDED = 3;

    /**
     * State: The binding is held back. The referenced type is not loaded.
     */
    const HELD_BACK = 4;

    /**
     * State: The binding is ignored. This happens if its type is duplicated.
     */
    const IGNORED = 5;

    /**
     * State: The binding does not match the constraints of the binding type.
     */
    const INVALID = 6;

    /**
     * Returns all binding states.
     *
     * @return int[] The binding states.
     */
    public static function all()
    {
        return array(
            self::NOT_LOADED,
            self::ENABLED,
            self::DISABLED,
            self::UNDECIDED,
            self::HELD_BACK,
            self::IGNORED,
            self::INVALID,
        );
    }

    /**
     * Detects the binding state of a binding.
     *
     * @param BindingDescriptor          $bindingDescriptor The binding
     *                                                      descriptor.
     * @param Package                    $package           The package that
     *                                                      contains the binding.
     * @param BindingTypeDescriptorStore $typeStore         The store with the
     *                                                      defined types.
     *
     * @return int The binding state.
     */
    public static function detect(BindingDescriptor $bindingDescriptor, Package $package, BindingTypeDescriptorStore $typeStore)
    {
        $installInfo = $package->getInstallInfo();
        $uuid = $bindingDescriptor->getUuid();
        $typeName = $bindingDescriptor->getTypeName();

        if (!$typeStore->existsAny($typeName)) {
            return self::HELD_BACK;
        }

        if ($typeStore->isDuplicate($typeName)) {
            return self::IGNORED;
        }

        if (count($bindingDescriptor->getViolations()) > 0) {
            return self::INVALID;
        }

        if ($package instanceof RootPackage) {
            return self::ENABLED;
        }

        if ($installInfo->hasDisabledBindingUuid($uuid)) {
            return self::DISABLED;
        }

        if ($installInfo->hasEnabledBindingUuid($uuid)) {
            return self::ENABLED;
        }

        return self::UNDECIDED;
    }

    /**
     * Must not be instantiated.
     */
    private function __construct() {}
}
