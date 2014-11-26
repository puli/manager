<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\InstallFile\Reader;

use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\InvalidConfigException;
use Puli\RepositoryManager\Package\InstallFile\InstallFile;

/**
 * Reads an install file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface InstallFileReaderInterface
{
    /**
     * Reads an install file.
     *
     * @param string $path The file path to read.
     *
     * @return InstallFile The install file.
     *
     * @throws FileNotFoundException If the file was not found.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function readInstallFile($path);
}
