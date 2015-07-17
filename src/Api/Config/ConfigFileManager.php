<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Config;

use Puli\Manager\Api\Environment\GlobalEnvironment;

/**
 * Manages changes to the global configuration file.
 *
 * Use this class to make persistent changes to the global config.json.
 * Whenever you call methods in this class, the changes will be written to disk.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ConfigFileManager extends ConfigManager
{
    /**
     * Returns the global environment.
     *
     * @return GlobalEnvironment The global environment.
     */
    public function getEnvironment();

    /**
     * Returns the managed configuration file.
     *
     * @return ConfigFile The configuration file.
     */
    public function getConfigFile();
}
