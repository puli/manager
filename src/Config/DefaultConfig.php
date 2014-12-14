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
            self::DUMP_DIR => '{$puli-dir}/dump',
            self::WRITE_REPO => '{$puli-dir}/resource-repository.php',
            self::READ_REPO => '{$write-repo}',
        ));
    }
}
