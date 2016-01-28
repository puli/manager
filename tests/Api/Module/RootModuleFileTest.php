<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Module;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Api\Module\RootModuleFile;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootModuleFileTest extends PHPUnit_Framework_TestCase
{
    const PLUGIN_CLASS = 'Puli\Manager\Tests\Api\Module\Fixtures\TestPlugin';

    const OTHER_PLUGIN_CLASS = 'Puli\Manager\Tests\Api\Module\Fixtures\OtherPlugin';

    /**
     * @var RootModuleFile
     */
    private $moduleFile;

    protected function setUp()
    {
        $this->moduleFile = new RootModuleFile();
    }

    public function testConfigInheritsFromBaseConfig()
    {
        $baseConfig = new Config();
        $moduleFile = new RootModuleFile(null, null, $baseConfig);

        $this->assertNotSame($baseConfig, $moduleFile->getConfig());

        $baseConfig->set(Config::PULI_DIR, 'puli-dir');

        $this->assertSame('puli-dir', $moduleFile->getConfig()->get(Config::PULI_DIR));
    }

    public function testDuplicatePluginClassesIgnored()
    {
        $this->moduleFile->addPluginClass(self::PLUGIN_CLASS);
        $this->moduleFile->addPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->moduleFile->getPluginClasses());
    }

    public function testAddPluginClassIgnoresLeadingSlash()
    {
        $this->moduleFile->addPluginClass('\\'.self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->moduleFile->getPluginClasses());
    }

    public function testSetPluginClasses()
    {
        $this->moduleFile->addPluginClass(self::PLUGIN_CLASS);
        $this->moduleFile->setPluginClasses(array(self::OTHER_PLUGIN_CLASS));

        $this->assertSame(array(self::OTHER_PLUGIN_CLASS), $this->moduleFile->getPluginClasses());
    }

    public function testSetPluginClassesToEmptyArray()
    {
        $this->moduleFile->addPluginClass(self::PLUGIN_CLASS);
        $this->moduleFile->setPluginClasses(array());

        $this->assertSame(array(), $this->moduleFile->getPluginClasses());
    }

    public function testAddPluginClasses()
    {
        $this->moduleFile->addPluginClass(self::PLUGIN_CLASS);
        $this->moduleFile->addPluginClasses(array(self::OTHER_PLUGIN_CLASS));

        $this->assertSame(array(self::PLUGIN_CLASS, self::OTHER_PLUGIN_CLASS), $this->moduleFile->getPluginClasses());
    }

    public function testRemovePluginClass()
    {
        $this->moduleFile->setPluginClasses(array(
            self::PLUGIN_CLASS,
            self::OTHER_PLUGIN_CLASS,
        ));
        $this->moduleFile->removePluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::OTHER_PLUGIN_CLASS), $this->moduleFile->getPluginClasses());
    }

    public function testRemovePluginClassIgnoresLeadingSlash()
    {
        $this->moduleFile->setPluginClasses(array(
            self::PLUGIN_CLASS,
            self::OTHER_PLUGIN_CLASS,
        ));
        $this->moduleFile->removePluginClass('\\'.self::PLUGIN_CLASS);

        $this->assertSame(array(self::OTHER_PLUGIN_CLASS), $this->moduleFile->getPluginClasses());
    }

    public function testRemovePluginClassDoesNothingIfNotFound()
    {
        $this->moduleFile->addPluginClass(self::PLUGIN_CLASS);
        $this->moduleFile->removePluginClass(self::OTHER_PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->moduleFile->getPluginClasses());
    }

    public function testClearPluginClasses()
    {
        $this->moduleFile->setPluginClasses(array(
            self::PLUGIN_CLASS,
            self::OTHER_PLUGIN_CLASS,
        ));
        $this->moduleFile->clearPluginClasses();

        $this->assertSame(array(), $this->moduleFile->getPluginClasses());
    }

    public function testHasPluginClass()
    {
        $this->assertFalse($this->moduleFile->hasPluginClass(self::PLUGIN_CLASS));

        $this->moduleFile->addPluginClass(self::PLUGIN_CLASS);

        $this->assertTrue($this->moduleFile->hasPluginClass(self::PLUGIN_CLASS));
    }

    public function testHasPluginClassIgnoresLeadingSlash()
    {
        $this->assertFalse($this->moduleFile->hasPluginClass('\\'.self::PLUGIN_CLASS));

        $this->moduleFile->addPluginClass(self::PLUGIN_CLASS);

        $this->assertTrue($this->moduleFile->hasPluginClass('\\'.self::PLUGIN_CLASS));
    }

    public function testHasPluginClasses()
    {
        $this->assertFalse($this->moduleFile->hasPluginClasses());

        $this->moduleFile->addPluginClass(self::PLUGIN_CLASS);

        $this->assertTrue($this->moduleFile->hasPluginClasses());
    }

    public function testAddInstallInfo()
    {
        $installInfo1 = new InstallInfo('vendor/module1', '/path/to/module1');
        $installInfo2 = new InstallInfo('vendor/module2', '/path/to/module2');

        $this->moduleFile->addInstallInfo($installInfo1);
        $this->moduleFile->addInstallInfo($installInfo2);

        $this->assertSame(array($installInfo1, $installInfo2), $this->moduleFile->getInstallInfos());
        $this->assertSame($installInfo1, $this->moduleFile->getInstallInfo('vendor/module1'));
        $this->assertSame($installInfo2, $this->moduleFile->getInstallInfo('vendor/module2'));
    }

    public function testSetInstallInfos()
    {
        $installInfo1 = new InstallInfo('vendor/module1', '/path/to/module1');
        $installInfo2 = new InstallInfo('vendor/module2', '/path/to/module2');

        $this->moduleFile->setInstallInfos(array($installInfo1, $installInfo2));

        $this->assertSame(array($installInfo1, $installInfo2), $this->moduleFile->getInstallInfos());
    }

    /**
     * @expectedException \Puli\Manager\Api\Module\NoSuchModuleException
     * @expectedExceptionMessage /foo/bar
     */
    public function testGetInstallInfosFailsIfNotFound()
    {
        $this->moduleFile->getInstallInfo('/foo/bar');
    }

    public function testRemoveInstallInfo()
    {
        $installInfo1 = new InstallInfo('vendor/module1', '/path/to/module1');
        $installInfo2 = new InstallInfo('vendor/module2', '/path/to/module2');

        $this->moduleFile->addInstallInfo($installInfo1);
        $this->moduleFile->addInstallInfo($installInfo2);

        $this->moduleFile->removeInstallInfo('vendor/module1');

        $this->assertSame(array($installInfo2), $this->moduleFile->getInstallInfos());
    }

    public function testRemoveInstallInfoIgnoresUnknownModuleName()
    {
        $installInfo1 = new InstallInfo('vendor/module1', '/path/to/module1');

        $this->moduleFile->addInstallInfo($installInfo1);

        $this->moduleFile->removeInstallInfo('foobar');

        $this->assertSame(array($installInfo1), $this->moduleFile->getInstallInfos());
    }

    public function testClearInstallInfos()
    {
        $installInfo1 = new InstallInfo('vendor/module1', '/path/to/module1');
        $installInfo2 = new InstallInfo('vendor/module2', '/path/to/module2');

        $this->moduleFile->addInstallInfo($installInfo1);
        $this->moduleFile->addInstallInfo($installInfo2);

        $this->moduleFile->clearInstallInfos();

        $this->assertSame(array(), $this->moduleFile->getInstallInfos());
    }

    public function testHasInstallInfo()
    {
        $this->assertFalse($this->moduleFile->hasInstallInfo('vendor/module'));

        $this->moduleFile->addInstallInfo(new InstallInfo('vendor/module', '/path/to/module'));

        $this->assertTrue($this->moduleFile->hasInstallInfo('vendor/module'));
    }

    public function testHasInstallInfos()
    {
        $this->assertFalse($this->moduleFile->hasInstallInfos());

        $this->moduleFile->addInstallInfo(new InstallInfo('vendor/module', '/path/to/module'));

        $this->assertTrue($this->moduleFile->hasInstallInfos());
    }
}
