<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\Config\Writer;

use Puli\RepositoryManager\IOException;
use Puli\RepositoryManager\Package\Config\PackageConfig;

/**
 * Writes package configuration to a file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface PackageConfigWriterInterface
{
    /**
     * Writes package configuration to a file.
     *
     * @param PackageConfig $config The configuration to write.
     * @param string        $path   The file path to write to.
     *
     * @throws IOException If the path cannot be written.
     */
    public function writePackageConfig(PackageConfig $config, $path);
}
