<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Package;

use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\InvalidConfigException;

/**
 * Serializes and unserializes package files.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface PackageFileSerializer
{
    /**
     * Serializes a package file.
     *
     * @param PackageFile $packageFile The package file.
     *
     * @return string The serialized package file.
     */
    public function serializePackageFile(PackageFile $packageFile);

    /**
     * Serializes a root package file.
     *
     * @param RootPackageFile $packageFile The root package file.
     *
     * @return string The serialized package file.
     */
    public function serializeRootPackageFile(RootPackageFile $packageFile);

    /**
     * Unserializes a package file.
     *
     * @param string      $serialized The serialized package file.
     * @param string|null $path       The path to the package file.
     *
     * @return PackageFile The package file.
     *
     * @throws InvalidConfigException      If the serialized text contains
     *                                     invalid configuration.
     * @throws UnsupportedVersionException If the version of the package file
     *                                     is not supported.
     */
    public function unserializePackageFile($serialized, $path = null);

    /**
     * Unserializes a root package file.
     *
     * @param string      $serialized The serialized package file.
     * @param string|null $path       The path to the package file.
     * @param Config      $baseConfig The configuration that the package will
     *                                inherit its configuration values from.
     *
     * @return RootPackageFile The root package file.
     *
     * @throws InvalidConfigException      If the serialized text contains
     *                                     invalid configuration.
     * @throws UnsupportedVersionException If the version of the package file
     *                                     is not supported.
     *                                     is not supported.
     */
    public function unserializeRootPackageFile($serialized, $path = null, Config $baseConfig = null);
}
