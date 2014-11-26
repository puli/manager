<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Plugin;

use Puli\PackageManager\Project\ProjectEnvironment;

/**
 * A plugin for the package manager.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface PluginInterface
{
    /**
     * Activates the plugin.
     *
     * @param ProjectEnvironment $environment The project environment.
     */
    public function activate(ProjectEnvironment $environment);
}
