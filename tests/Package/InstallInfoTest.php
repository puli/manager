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
    public function testCreate()
    {
        $installInfo = new InstallInfo('package', '/path');
        $installInfo->setInstaller('Composer');

        $this->assertSame('package', $installInfo->getPackageName());
        $this->assertSame('/path', $installInfo->getInstallPath());
        $this->assertSame('Composer', $installInfo->getInstaller());
    }
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallPathNotString()
    {
        new InstallInfo('package', 12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallPathEmpty()
    {
        new InstallInfo('package', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameNotString()
    {
        new InstallInfo(12345, '/path');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameEmpty()
    {
        new InstallInfo('', '/path');
    }
}
