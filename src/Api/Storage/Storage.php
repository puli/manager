<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Storage;

use Puli\Manager\Api\FileNotFoundException;

/**
 * Stores files.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface Storage
{
    /**
     * Reads a file.
     *
     * @param string $path The file path.
     *
     * @return string The file contents.
     *
     * @throws FileNotFoundException If the file cannot be found.
     * @throws ReadException         If the file cannot be read.
     */
    public function read($path);

    /**
     * Writes a file.
     *
     * @param string $path     The file path.
     * @param string $contents The contents to write to the file.
     *
     * @return int The number of bytes written to the file.
     *
     * @throws WriteException If the file cannot be written.
     */
    public function write($path, $contents);

    /**
     * Returns whether a path exists.
     *
     * @param string $path The file path.
     *
     * @return bool Returns `true` if the path exists and `false` otherwise.
     */
    public function exists($path);
}
