<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Installer;

/**
 * Installs resources via symbolic links on the local filesystem.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SymlinkInstaller extends CopyInstaller
{
    /**
     * @var bool
     */
    protected $symlinks = true;
}
