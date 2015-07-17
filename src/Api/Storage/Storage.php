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
     * @throws StorageException If the file cannot be read.
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
     * @throws StorageException If the file cannot be written.
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
