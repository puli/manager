<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Event;

use Puli\RepositoryManager\Package\PackageFile\PackageFile;
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
