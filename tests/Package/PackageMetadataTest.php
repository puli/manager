<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package;

use Puli\RepositoryManager\Package\PackageMetadata;

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

    public function testSetName()
    {
        $metadata = new PackageMetadata('/path');
        $metadata->setName('name');

        $this->assertSame('name', $metadata->getName());

        $metadata->setName(null);

        $this->assertNull($metadata->getName());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameNotString()
    {
        $metadata = new PackageMetadata('/path');

        $metadata->setName(12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameEmpty()
    {
        $metadata = new PackageMetadata('/path');

        $metadata->setName('');
    }
}
