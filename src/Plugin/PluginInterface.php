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

use Puli\PackageManager\PackageManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
     * @param PackageManager           $manager    The package manager.
     * @param EventDispatcherInterface $dispatcher The manager's event dispatcher.
     */
    public function activate(PackageManager $manager, EventDispatcherInterface $dispatcher);
}
