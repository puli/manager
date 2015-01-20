<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Event;

use Puli\RepositoryManager\Api\Package\PackageFile;
use Symfony\Component\EventDispatcher\Event;

/**
 * Dispatched when a package file is read or written.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileEvent extends Event
{
    /**
     * @var PackageFile
     */
    private $packageFile;

    /**
     * Creates the event.
     *
     * @param PackageFile $packageFile The package file.
     */
    public function __construct(PackageFile $packageFile)
    {
        $this->packageFile = $packageFile;
    }

    /**
     * Returns the package file.
     *
     * @return PackageFile The package file.
     */
    public function getPackageFile()
    {
        return $this->packageFile;
    }
}
