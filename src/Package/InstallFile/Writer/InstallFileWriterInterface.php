<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\InstallFile\Writer;

use Puli\RepositoryManager\IOException;
use Puli\RepositoryManager\Package\InstallFile\InstallFile;

/**
 * Writes an install file to the file system.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface InstallFileWriterInterface
{
    /**
     * Writes an install file to the file system.
     *
     * @param InstallFile $installFile The install file to write.
     * @param string      $path        The file path to write to.
     *
     * @throws IOException If the path cannot be written.
     */
    public function writeInstallFile(InstallFile $installFile, $path);
}
