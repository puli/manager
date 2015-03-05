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
     * @param Puli $puli The {@link Puli} instance.
     */
    public function activate(Puli $puli);
}
