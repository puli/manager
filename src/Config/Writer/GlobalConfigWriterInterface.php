<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Config\Writer;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\IOException;

/**
 * Writes global configuration to a file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface GlobalConfigWriterInterface
{
    /**
     * Writes global configuration to a file.
     *
     * @param GlobalConfig $config The configuration to write.
     * @param string       $path   The file path to write to.
     *
     * @throws IOException If the path cannot be written.
     */
    public function writeGlobalConfig(GlobalConfig $config, $path);
}
