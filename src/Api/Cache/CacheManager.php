<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Cache;

use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Module\ModuleProvider;

/**
 * Manages cached packages information.
 *
 * @since  1.0
 *
 * @author Mateusz Sojda <mateusz@sojda.pl>
 */
interface CacheManager extends ModuleProvider
{
    /**
     * Returns the manager's context.
     *
     * @return ProjectContext The project context.
     */
    public function getContext();

    /**
     * Reads and returns cache file.
     *
     * @return CacheFile The cache file.
     */
    public function getCacheFile();

    /**
     * Refreshes the cache file if it contains outdated informations or cache file doesn't exist.
     */
    public function refreshCacheFile();
}
