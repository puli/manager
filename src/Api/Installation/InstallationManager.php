<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Installation;

use Puli\Manager\Api\Asset\AssetMapping;
use Puli\Repository\Api\Resource\Resource;

/**
 * Manages the installation of resources.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface InstallationManager
{
    /**
     * Prepares the installation of an asset mapping.
     *
     * If the preparation succeeds, this method returns an
     * {@link InstallationParams} instance which can be passed to
     * {@link executeInstallation()}.
     *
     * @param AssetMapping $mapping The asset mapping.
     *
     * @return InstallationParams The installation parameters.
     *
     * @throws NotInstallableException If the installation is not possible.
     */
    public function prepareInstallation(AssetMapping $mapping);

    /**
     * Installs a resource on its server.
     *
     * @param Resource           $resource The resource to install.
     * @param InstallationParams $params   The installation parameters returned
     *                                     by {@link prepareInstallation()}.
     *
     * @throws NotInstallableException If the installation fails.
     */
    public function installResource(Resource $resource, InstallationParams $params);
}
