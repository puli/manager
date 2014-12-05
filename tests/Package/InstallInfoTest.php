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

use Puli\RepositoryManager\Package\InstallInfo;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallInfoTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallPathNotString()
    {
        new InstallInfo(12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallPathEmpty()
    {
        new InstallInfo('');
    }

    public function testSetName()
    {
        $installInfo = new InstallInfo('/path');
        $installInfo->setPackageName('name');

        $this->assertSame('name', $installInfo->getPackageName());

        $installInfo->setPackageName(null);

        $this->assertNull($installInfo->getPackageName());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameNotString()
    {
        $installInfo = new InstallInfo('/path');

        $installInfo->setPackageName(12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameEmpty()
    {
        $installInfo = new InstallInfo('/path');

        $installInfo->setPackageName('');
    }
}
