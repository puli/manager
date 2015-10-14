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
 * Loads configuration values from context variables.
 *
 * Configuration keys that are not set as context variables are loaded from
 * the base configuration passed to the constructor.
 *
 * Currently, only the context variable "PULI_DIR" is supported by this
 * class.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class EnvConfig extends Config
{
    /**
     * Creates the configuration.
     *
     * @param Config|null $baseConfig The base configuration to use for unset
     *                                values.
     */
    public function __construct(Config $baseConfig = null)
    {
        parent::__construct($baseConfig);

        if (false !== ($puliDir = getenv('PULI_DIR'))) {
            $this->set(Config::PULI_DIR, $puliDir);
        }
    }
}
