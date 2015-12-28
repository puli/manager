<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Filesystem;

use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\Storage\ReadException;
use Puli\Manager\Api\Storage\Storage;
use Puli\Manager\Assert\Assert;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

/**
 * Stores files on the filesystem.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FilesystemStorage implements Storage
{
    /**
     * {@inheritdoc}
     */
    public function read($path)
    {
        Assert::notEmpty($path, 'Cannot read an empty path.');

        if (!file_exists($path)) {
            throw new FileNotFoundException(sprintf('Cannot read %s: File not found.', $path));
        }

        if (is_dir($path)) {
            throw new ReadException(sprintf('Cannot read %s: Is a directory.', $path));
        }

        if (false === ($contents = @file_get_contents($path))) {
            $error = error_get_last();

            throw new ReadException(sprintf('Could not read %s: %s.', $path, $error['message']));
        }

        return $contents;
    }

    /**
     * {@inheritdoc}
     */
    public function write($path, $contents)
    {
        Assert::notEmpty($path, 'Cannot write to an empty path.');

        if (is_dir($path)) {
            throw new ReadException(sprintf('Cannot write %s: Is a directory.', $path));
        }

        if (!is_dir($dir = Path::getDirectory($path))) {
            $filesystem = new Filesystem();
            $filesystem->mkdir($dir);
        }

        if (false === ($numBytes = @file_put_contents($path, $contents))) {
            $error = error_get_last();

            throw new ReadException(sprintf('Could not write %s: %s.', $path, $error['message']));
        }

        return $numBytes;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($path)
    {
        return file_exists($path);
    }
}
