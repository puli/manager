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
            self::INSTALL_FILE => '{$puli-dir}/install-file.json',
            self::REPO_DUMP_DIR => '{$puli-dir}/repo',
            self::REPO_DUMP_FILE => '{$puli-dir}/resource-repository.php',
            self::REPO_FILE => '{$repo-dump-file}',
        ));
    }
}
