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
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\RootPackageFile;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageFileTest extends PHPUnit_Framework_TestCase
{
    const PLUGIN_CLASS = 'Puli\Manager\Tests\Api\Package\Fixtures\TestPlugin';

    const OTHER_PLUGIN_CLASS = 'Puli\Manager\Tests\Api\Package\Fixtures\OtherPlugin';

    /**
     * @var RootPackageFile
     */
    private $packageFile;

    protected function setUp()
    {
        $this->packageFile = new RootPackageFile();
    }

    public function testConfigInheritsFromBaseConfig()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, null, $baseConfig);

        $this->assertNotSame($baseConfig, $packageFile->getConfig());

        $baseConfig->set(Config::PULI_DIR, 'puli-dir');

        $this->assertSame('puli-dir', $packageFile->getConfig()->get(Config::PULI_DIR));
    }

    public function testDuplicatePluginClassesIgnored()
    {
        $this->packageFile->addPluginClass(self::PLUGIN_CLASS);
        $this->packageFile->addPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->packageFile->getPluginClasses());
    }

    public function testAddPluginClassIgnoresLeadingSlash()
    {
        $this->packageFile->addPluginClass('\\'.self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->packageFile->getPluginClasses());
    }

    public function testSetPluginClasses()
    {
        $this->packageFile->addPluginClass(self::PLUGIN_CLASS);
        $this->packageFile->setPluginClasses(array(self::OTHER_PLUGIN_CLASS));

        $this->assertSame(array(self::OTHER_PLUGIN_CLASS), $this->packageFile->getPluginClasses());
    }

    public function testSetPluginClassesToEmptyArray()
    {
        $this->packageFile->addPluginClass(self::PLUGIN_CLASS);
        $this->packageFile->setPluginClasses(array());

        $this->assertSame(array(), $this->packageFile->getPluginClasses());
    }

    public function testAddPluginClasses()
    {
        $this->packageFile->addPluginClass(self::PLUGIN_CLASS);
        $this->packageFile->addPluginClasses(array(self::OTHER_PLUGIN_CLASS));

        $this->assertSame(array(self::PLUGIN_CLASS, self::OTHER_PLUGIN_CLASS), $this->packageFile->getPluginClasses());
    }

    public function testRemovePluginClass()
    {
        $this->packageFile->setPluginClasses(array(
            self::PLUGIN_CLASS,
            self::OTHER_PLUGIN_CLASS,
        ));
        $this->packageFile->removePluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::OTHER_PLUGIN_CLASS), $this->packageFile->getPluginClasses());
    }

    public function testRemovePluginClassIgnoresLeadingSlash()
    {
        $this->packageFile->setPluginClasses(array(
            self::PLUGIN_CLASS,
            self::OTHER_PLUGIN_CLASS,
        ));
        $this->packageFile->removePluginClass('\\'.self::PLUGIN_CLASS);

        $this->assertSame(array(self::OTHER_PLUGIN_CLASS), $this->packageFile->getPluginClasses());
    }

    public function testRemovePluginClassDoesNothingIfNotFound()
    {
        $this->packageFile->addPluginClass(self::PLUGIN_CLASS);
        $this->packageFile->removePluginClass(self::OTHER_PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->packageFile->getPluginClasses());
    }

    public function testClearPluginClasses()
    {
        $this->packageFile->setPluginClasses(array(
            self::PLUGIN_CLASS,
            self::OTHER_PLUGIN_CLASS,
        ));
        $this->packageFile->clearPluginClasses();

        $this->assertSame(array(), $this->packageFile->getPluginClasses());
    }

    public function testHasPluginClass()
    {
        $this->assertFalse($this->packageFile->hasPluginClass(self::PLUGIN_CLASS));

        $this->packageFile->addPluginClass(self::PLUGIN_CLASS);

        $this->assertTrue($this->packageFile->hasPluginClass(self::PLUGIN_CLASS));
    }

    public function testHasPluginClassIgnoresLeadingSlash()
    {
        $this->assertFalse($this->packageFile->hasPluginClass('\\'.self::PLUGIN_CLASS));

        $this->packageFile->addPluginClass(self::PLUGIN_CLASS);

        $this->assertTrue($this->packageFile->hasPluginClass('\\'.self::PLUGIN_CLASS));
    }

    public function testHasPluginClasses()
    {
        $this->assertFalse($this->packageFile->hasPluginClasses());

        $this->packageFile->addPluginClass(self::PLUGIN_CLASS);

        $this->assertTrue($this->packageFile->hasPluginClasses());
    }

    public function testAddInstallInfo()
    {
        $installInfo1 = new InstallInfo('vendor/package1', '/path/to/package1');
        $installInfo2 = new InstallInfo('vendor/package2', '/path/to/package2');

        $this->packageFile->addInstallInfo($installInfo1);
        $this->packageFile->addInstallInfo($installInfo2);

        $this->assertSame(array($installInfo1, $installInfo2), $this->packageFile->getInstallInfos());
        $this->assertSame($installInfo1, $this->packageFile->getInstallInfo('vendor/package1'));
        $this->assertSame($installInfo2, $this->packageFile->getInstallInfo('vendor/package2'));
    }

    public function testSetInstallInfos()
    {
        $installInfo1 = new InstallInfo('vendor/package1', '/path/to/package1');
        $installInfo2 = new InstallInfo('vendor/package2', '/path/to/package2');

        $this->packageFile->setInstallInfos(array($installInfo1, $installInfo2));

        $this->assertSame(array($installInfo1, $installInfo2), $this->packageFile->getInstallInfos());
    }

    /**
     * @expectedException \Puli\Manager\Api\Package\NoSuchPackageException
     * @expectedExceptionMessage /foo/bar
     */
    public function testGetInstallInfosFailsIfNotFound()
    {
        $this->packageFile->getInstallInfo('/foo/bar');
    }

    public function testRemoveInstallInfo()
    {
        $installInfo1 = new InstallInfo('vendor/package1', '/path/to/package1');
        $installInfo2 = new InstallInfo('vendor/package2', '/path/to/package2');

        $this->packageFile->addInstallInfo($installInfo1);
        $this->packageFile->addInstallInfo($installInfo2);

        $this->packageFile->removeInstallInfo('vendor/package1');

        $this->assertSame(array($installInfo2), $this->packageFile->getInstallInfos());
    }

    public function testRemoveInstallInfoIgnoresUnknownPackageName()
    {
        $installInfo1 = new InstallInfo('vendor/package1', '/path/to/package1');

        $this->packageFile->addInstallInfo($installInfo1);

        $this->packageFile->removeInstallInfo('foobar');

        $this->assertSame(array($installInfo1), $this->packageFile->getInstallInfos());
    }

    public function testHasInstallInfo()
    {
        $this->assertFalse($this->packageFile->hasInstallInfo('vendor/package'));

        $this->packageFile->addInstallInfo(new InstallInfo('vendor/package', '/path/to/package'));

        $this->assertTrue($this->packageFile->hasInstallInfo('vendor/package'));
    }
}
