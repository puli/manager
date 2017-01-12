<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Config;

use Puli\Manager\Api\Config\Config;

/**
 * Stores default configuration values.
 *
 * @since  1.0
 *
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
            self::FACTORY_OUT_CLASS => 'Puli\GeneratedPuliFactory',
            self::FACTORY_OUT_FILE => '{$puli-dir}/GeneratedPuliFactory.php',
            self::FACTORY_IN_CLASS => '{$factory.out.class}',
            self::FACTORY_IN_FILE => '{$factory.out.file}',
            self::REPOSITORY_TYPE => 'json',
            self::REPOSITORY_PATH => '{$puli-dir}/path-mappings.json',
            self::REPOSITORY_SYMLINK => true,
            self::REPOSITORY_OPTIMIZE => false,
            self::REPOSITORY_STORE_CACHE => true,
            self::CHANGE_STREAM_TYPE => 'json',
            self::CHANGE_STREAM_PATH => '{$puli-dir}/change-stream.json',
            self::CHANGE_STREAM_STORE_CACHE => true,
            self::DISCOVERY_TYPE => 'json',
            self::DISCOVERY_PATH => '{$puli-dir}/bindings.json',
            self::DISCOVERY_STORE_CACHE => true,
            self::CACHE_FILE => '{$puli-dir}/puli.json.cache',
        ));
    }
}
