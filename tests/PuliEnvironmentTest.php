<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\ConfigManager;
use Puli\PackageManager\PuliEnvironment;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PuliEnvironmentTest extends \PHPUnit_Framework_TestCase
{
    const PLUGIN_CLASS = 'Puli\PackageManager\Tests\Config\Fixtures\TestPlugin';

    const OTHER_PLUGIN_CLASS = 'Puli\PackageManager\Tests\Config\Fixtures\OtherPlugin';

    /**
     * @var string
     */
    private $tempHome;

    /**
     * @var PuliEnvironment
     */
    private $env;

    /**
     * @var GlobalConfig
     */
    private $globalConfig;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ConfigManager
     */
    private $configManager;

    protected function setUp()
    {
        while (false === mkdir($this->tempHome = sys_get_temp_dir().'/puli-manager/PackageManagerTest_home'.rand(10000, 99999), 0777, true)) {}

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures/home', $this->tempHome);

        $this->globalConfig = new GlobalConfig();
        $this->configManager = $this->getMockBuilder('Puli\PackageManager\ConfigManager')
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempHome);

        // Unset env variables
        putenv('PULI_HOME');
        putenv('HOME');
        putenv('APPDATA');
    }

    public function testParseHomeDirectory()
    {
        putenv('HOME='.$this->tempHome);

        $this->assertSame($this->tempHome.'/.puli', PuliEnvironment::parseHomeDirectory());
    }

    public function testParseHomeDirectoryBackslashes()
    {
        putenv('HOME='.strtr($this->tempHome, '/', '\\'));

        $this->assertSame($this->tempHome.'/.puli', PuliEnvironment::parseHomeDirectory());
    }

    public function testParseOverwrittenHomeDirectory()
    {
        putenv('HOME=/path/to/home');
        putenv('PULI_HOME='.$this->tempHome.'/custom-home');

        $this->assertSame($this->tempHome.'/custom-home', PuliEnvironment::parseHomeDirectory());
    }

    public function testParseOverwrittenHomeDirectoryBackslashes()
    {
        putenv('HOME=\path\to\home');
        putenv('PULI_HOME='.strtr($this->tempHome, '/', '\\').'\custom-home');

        $this->assertSame($this->tempHome.'/custom-home', PuliEnvironment::parseHomeDirectory());
    }

    public function testParseHomeDirectoryOnWindows()
    {
        putenv('APPDATA='.$this->tempHome);

        $this->assertSame($this->tempHome.'/Puli', PuliEnvironment::parseHomeDirectory());
    }

    public function testParseHomeDirectoryOnWindowsBackslashes()
    {
        putenv('APPDATA='.strtr($this->tempHome, '/', '\\'));

        $this->assertSame($this->tempHome.'/Puli', PuliEnvironment::parseHomeDirectory());
    }

    public function testParseOverwrittenHomeDirectoryOnWindows()
    {
        putenv('APPDATA=C:/path/to/home');
        putenv('PULI_HOME='.$this->tempHome.'/custom-home');

        $this->assertSame($this->tempHome.'/custom-home', PuliEnvironment::parseHomeDirectory());
    }

    public function testParseOverwrittenHomeDirectoryOnWindowsBackslashes()
    {
        putenv('APPDATA=C:\path\to\home');
        putenv('PULI_HOME='.strtr($this->tempHome, '/', '\\').'\custom-home');

        $this->assertSame($this->tempHome.'/custom-home', PuliEnvironment::parseHomeDirectory());
    }

    public function testFailIfNoHomeDirectoryFound()
    {
        $isWin = defined('PHP_WINDOWS_VERSION_MAJOR');

        // Mention correct variable in the exception message
        $this->setExpectedException('\Puli\PackageManager\BootstrapException', $isWin ? 'APPDATA' : ' HOME ');

        PuliEnvironment::parseHomeDirectory();
    }

    /**
     * @expectedException \Puli\PackageManager\BootstrapException
     * @expectedExceptionMessage PULI_HOME
     */
    public function testFailIfHomeNotADirectory()
    {
        putenv('PULI_HOME='.$this->tempHome.'/some-file');

        PuliEnvironment::parseHomeDirectory();
    }

    /**
     * @expectedException \Puli\PackageManager\BootstrapException
     * @expectedExceptionMessage HOME
     */
    public function testFailIfLinuxHomeNotADirectory()
    {
        putenv('HOME='.$this->tempHome.'/some-file');

        PuliEnvironment::parseHomeDirectory();
    }

    /**
     * @expectedException \Puli\PackageManager\BootstrapException
     * @expectedExceptionMessage APPDATA
     */
    public function testFailIfWindowsHomeNotADirectory()
    {
        putenv('APPDATA='.$this->tempHome.'/some-file');

        PuliEnvironment::parseHomeDirectory();
    }

    public function testDenyWebAccess()
    {
        $this->assertFileNotExists($this->tempHome.'/.htaccess');

        PuliEnvironment::denyWebAccess($this->tempHome);

        // Directory is protected
        $this->assertFileExists($this->tempHome.'/.htaccess');
        $this->assertSame('Deny from all', file_get_contents($this->tempHome.'/.htaccess'));
    }

    public function testReadConfigOnConstruct()
    {
        $globalConfig = new GlobalConfig();
        $globalConfig->addPluginClass(self::PLUGIN_CLASS);

        $this->configManager->expects($this->once())
            ->method('loadGlobalConfig')
            ->with($this->tempHome.'/config.json')
            ->will($this->returnValue($globalConfig));

        $this->configManager->expects($this->never())
            ->method('saveGlobalConfig');

        $env = new PuliEnvironment($this->tempHome, $this->configManager, $this->configManager);

        $this->assertSame(array(self::PLUGIN_CLASS), $env->getGlobalPluginClasses());
    }

    public function testInstallGlobalPlugin()
    {
        $this->initEnv();

        $this->assertSame(array(), $this->env->getGlobalPluginClasses());

        $this->configManager->expects($this->once())
            ->method('saveGlobalConfig')
            ->with($this->globalConfig)
            ->will($this->returnCallback(function (GlobalConfig $config) {
                \PHPUnit_Framework_Assert::assertSame(array(self::PLUGIN_CLASS), $config->getPluginClasses());
            }));

        $this->env->installGlobalPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->env->getGlobalPluginClasses());
    }

    public function testInstallGlobalDoesNothingIfPluginExistsGlobally()
    {
        $this->initEnv();

        $this->globalConfig->addPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->env->getGlobalPluginClasses());

        $this->configManager->expects($this->never())
            ->method('saveGlobalConfig');

        $this->env->installGlobalPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->env->getGlobalPluginClasses());
    }

    public function testIsPluginClassInstalled()
    {
        $this->initEnv();

        $this->globalConfig->addPluginClass(self::PLUGIN_CLASS);

        $this->assertTrue($this->env->isGlobalPluginClassInstalled(self::PLUGIN_CLASS));
        $this->assertFalse($this->env->isGlobalPluginClassInstalled(self::OTHER_PLUGIN_CLASS));

        $this->env->installGlobalPluginClass(self::OTHER_PLUGIN_CLASS);

        $this->assertTrue($this->env->isGlobalPluginClassInstalled(self::PLUGIN_CLASS));
        $this->assertTrue($this->env->isGlobalPluginClassInstalled(self::OTHER_PLUGIN_CLASS));
    }

    private function initEnv()
    {
        $this->configManager->expects($this->once())
            ->method('loadGlobalConfig')
            ->with($this->tempHome.'/config.json')
            ->will($this->returnValue($this->globalConfig));

        $this->env = new PuliEnvironment(
            $this->tempHome,
            $this->configManager,
            $this->configManager
        );
    }
}
