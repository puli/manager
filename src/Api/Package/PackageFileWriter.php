<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Package;

use Puli\Manager\Api\IOException;

/**
 * Writes package files.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface PackageFileWriter
{
    /**
     * Writes a package file.
     *
     * @param PackageFile $packageFile The package file to write.
     * @param string      $path        The file path to write to.
     *
     * @throws IOException If the path cannot be written.
     */
    public function writePackageFile(PackageFile $packageFile, $path);
}
