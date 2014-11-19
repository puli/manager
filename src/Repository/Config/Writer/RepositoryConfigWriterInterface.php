<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Repository\Config\Writer;

use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;

/**
 * Writes package repository configuration to a data source.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface RepositoryConfigWriterInterface
{
    /**
     * Writes repository configuration to a data source.
     *
     * @param PackageRepositoryConfig $config      The configuration to write.
     * @param mixed                   $destination The destination to write to.
     */
    public function writeRepositoryConfig(PackageRepositoryConfig $config, $destination);
}
