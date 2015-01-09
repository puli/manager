<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\PackageFile;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageFileTest extends PHPUnit_Framework_TestCase
{
    const PLUGIN_CLASS = 'Puli\RepositoryManager\Tests\Package\PackageFile\Fixtures\TestPlugin';

    const OTHER_PLUGIN_CLASS = 'Puli\RepositoryManager\Tests\Package\PackageFile\Fixtures\OtherPlugin';

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

    /**
     * @expectedException \Puli\RepositoryManager\InvalidConfigException
     */
    public function testPluginClassMustBeExistingClass()
    {
        $this->packageFile->addPluginClass('\Puli\Foobar');
    }

    /**
     * @expectedException \Puli\RepositoryManager\InvalidConfigException
     */
    public function testPluginClassMustImplementPluginInterface()
    {
        $this->packageFile->addPluginClass('\stdClass');
    }

    /**
     * @expectedException \Puli\RepositoryManager\InvalidConfigException
     */
    public function testPluginClassMustHaveNoArgConstructor()
    {
        $this->packageFile->addPluginClass(__NAMESPACE__.'\Fixtures\TestPluginWithoutNoArgConstructor');
    }

    public function testPluginClassMayHaveNoConstructor()
    {
        $this->packageFile->addPluginClass(__NAMESPACE__.'\Fixtures\TestPluginWithoutConstructor');

        $this->assertSame(array(__NAMESPACE__.'\Fixtures\TestPluginWithoutConstructor'), $this->packageFile->getPluginClasses());
    }

    /**
     * @expectedException \Puli\RepositoryManager\InvalidConfigException
     */
    public function testPluginClassMustNotBeInterface()
    {
        $this->packageFile->addPluginClass(__NAMESPACE__.'\Fixtures\TestPluginInterface');
    }

    /**
     * @expectedException \Puli\RepositoryManager\InvalidConfigException
     * @expectedExceptionMessage trait
     */
    public function testPluginClassMustNotBeTrait()
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            $this->markTestSkipped('PHP >= 5.4.0 only');

            return;
        }

        $this->packageFile->addPluginClass(__NAMESPACE__.'\Fixtures\TestPluginTrait');
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
        $this->packageFile->setPluginClasses(array(
            self::PLUGIN_CLASS,
            self::OTHER_PLUGIN_CLASS,
        ));

        $this->assertSame(array(self::PLUGIN_CLASS, self::OTHER_PLUGIN_CLASS), $this->packageFile->getPluginClasses());
    }

    public function testSetPluginClassesToEmptyArray()
    {
        $this->packageFile->addPluginClass(self::PLUGIN_CLASS);
        $this->packageFile->setPluginClasses(array());

        $this->assertSame(array(), $this->packageFile->getPluginClasses());
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
     * @expectedException \Puli\RepositoryManager\Package\NoSuchPackageException
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
