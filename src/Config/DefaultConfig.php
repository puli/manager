<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Config;

use Puli\RepositoryManager\Api\Config\Config;

/**
 * Stores default configuration values.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DefaultConfig extends Config
{
    /**
     * Creates the default configuration.
     */
    public function __construct()
    {
        parent::__construct(null, array(
            self::PULI_DIR => '.puli',
            self::FACTORY_AUTO_GENERATE => true,
            self::FACTORY_CLASS => 'Puli\GeneratedPuliFactory',
            self::FACTORY_FILE => '{$puli-dir}/GeneratedPuliFactory.php',
            self::REPOSITORY_TYPE => 'filesystem',
            self::REPOSITORY_PATH => '{$puli-dir}/repository',
            self::REPOSITORY_SYMLINK => true,
            self::DISCOVERY_TYPE => 'key-value-store',
            self::DISCOVERY_STORE_TYPE => 'json-file',
            self::DISCOVERY_STORE_PATH => '{$puli-dir}/bindings.json',
            self::DISCOVERY_STORE_CACHE => true,
        ));
    }
}
