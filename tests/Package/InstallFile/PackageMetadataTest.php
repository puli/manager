<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\InstallFile;

use Puli\RepositoryManager\Package\InstallFile\PackageMetadata;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageMetadataTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallPathNotString()
    {
        new PackageMetadata(12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallPathEmpty()
    {
        new PackageMetadata('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNewNotBoolean()
    {
        $metadata = new PackageMetadata('/path');

        $metadata->setNew(12345);
    }
}
