<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Package;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\Package\InstallInfo;
use Rhumsaa\Uuid\Uuid;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallInfoTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $installInfo = new InstallInfo('vendor/package', '/path');
        $installInfo->setInstallerName('Composer');

        $this->assertSame('vendor/package', $installInfo->getPackageName());
        $this->assertSame('/path', $installInfo->getInstallPath());
        $this->assertSame('Composer', $installInfo->getInstallerName());
        $this->assertSame(Environment::PROD, $installInfo->getEnvironment());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallPathNotString()
    {
        new InstallInfo('vendor/package', 12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallPathEmpty()
    {
        new InstallInfo('vendor/package', '');
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

    public function testAddDisabledBindingUuid()
    {
        $installInfo = new InstallInfo('vendor/package', '/path');
        $uuid = Uuid::uuid4();

        $installInfo->addDisabledBindingUuid($uuid);

        $this->assertSame(array($uuid), $installInfo->getDisabledBindingUuids());
    }

    public function testAddDisabledBindingUuidIgnoresDuplicates()
    {
        $installInfo = new InstallInfo('vendor/package', '/path');
        $uuid = Uuid::uuid4();

        $installInfo->addDisabledBindingUuid($uuid);
        $installInfo->addDisabledBindingUuid($uuid);

        $this->assertSame(array($uuid), $installInfo->getDisabledBindingUuids());
    }

    public function testRemoveDisabledBindingUuid()
    {
        $installInfo = new InstallInfo('vendor/package', '/path');
        $uuid = Uuid::uuid4();

        $installInfo->addDisabledBindingUuid($uuid);
        $installInfo->removeDisabledBindingUuid($uuid);

        $this->assertSame(array(), $installInfo->getDisabledBindingUuids());
    }

    public function testRemoveDisabledBindingUuidIgnoresUnknown()
    {
        $installInfo = new InstallInfo('vendor/package', '/path');
        $uuid = Uuid::uuid4();

        $installInfo->removeDisabledBindingUuid($uuid);

        $this->assertSame(array(), $installInfo->getDisabledBindingUuids());
    }

    public function testSetEnvironment()
    {
        $installInfo = new InstallInfo('vendor/package', '/path');
        $installInfo->setEnvironment(Environment::PROD);

        $this->assertSame(Environment::PROD, $installInfo->getEnvironment());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInvalidEnvironment()
    {
        $installInfo = new InstallInfo('vendor/package', '/path');
        $installInfo->setEnvironment('foo');
    }
}
