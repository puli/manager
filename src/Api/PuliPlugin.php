<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api;

use Puli\RepositoryManager\Api\Environment\ProjectEnvironment;

/**
 * A plugin for the repository manager.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface PuliPlugin
{
    /**
     * Activates the plugin.
     *
     * @param ProjectEnvironment $environment The project environment.
     */
    public function activate(ProjectEnvironment $environment);
}
