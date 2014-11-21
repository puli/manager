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
use Puli\PackageManager\Config\Reader\GlobalConfigReaderInterface;
use Puli\PackageManager\Config\Writer\GlobalConfigWriterInterface;
use Puli\PackageManager\FileNotFoundException;
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
     * @var \PHPUnit_Framework_MockObject_MockObject|GlobalConfigReaderInterface
     */
    private $configReader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|GlobalConfigWriterInterface
     */
    private $configWriter;

    protected function setUp()
    {
        while (false === mkdir($this->tempHome = sys_get_temp_dir().'/puli-manager/PackageManagerTest_home'.rand(10000, 99999), 0777, true)) {}

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures/home', $this->tempHome);

        $this->globalConfig = new GlobalConfig();
        $this->configReader = $this->getMock('Puli\PackageManager\Config\Reader\GlobalConfigReaderInterface');
        $this->configWriter = $this->getMock('Puli\PackageManager\Config\Writer\GlobalConfigWriterInterface');
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

    public function testInitHomeDirectory()
    {
        putenv('HOME='.$this->tempHome);

        $env = PuliEnvironment::createFromSystem();

        $this->assertTrue(is_dir($this->tempHome.'/.puli'));
        $this->assertSame($this->tempHome.'/.puli', $env->getHomeDirectory());
    }

    public function testInitHomeDirectoryBackslashes()
    {
        putenv('HOME='.strtr($this->tempHome, '/', '\\'));

        $env = PuliEnvironment::createFromSystem();

        $this->assertTrue(is_dir($this->tempHome.'/.puli'));
        $this->assertSame($this->tempHome.'/.puli', $env->getHomeDirectory());
    }

    public function testGetOverwrittenHomeDirectory()
    {
        putenv('HOME=/path/to/home');
        putenv('PULI_HOME='.$this->tempHome.'/custom-home');

        $env = PuliEnvironment::createFromSystem();

        $this->assertTrue(is_dir($this->tempHome.'/custom-home'));
        $this->assertSame($this->tempHome.'/custom-home', $env->getHomeDirectory());
    }

    public function testGetOverwrittenHomeDirectoryBackslashes()
    {
        putenv('HOME=\path\to\home');
        putenv('PULI_HOME='.strtr($this->tempHome, '/', '\\').'\custom-home');

        $env = PuliEnvironment::createFromSystem();

        $this->assertTrue(is_dir($this->tempHome.'/custom-home'));
        $this->assertSame($this->tempHome.'/custom-home', $env->getHomeDirectory());
    }

    public function testInitHomeDirectoryOnWindows()
    {
        putenv('APPDATA='.$this->tempHome);

        $env = PuliEnvironment::createFromSystem();

        $this->assertTrue(is_dir($this->tempHome.'/Puli'));
        $this->assertSame($this->tempHome.'/Puli', $env->getHomeDirectory());
    }

    public function testInitHomeDirectoryOnWindowsBackslashes()
    {
        putenv('APPDATA='.strtr($this->tempHome, '/', '\\'));

        $env = PuliEnvironment::createFromSystem();

        $this->assertTrue(is_dir($this->tempHome.'/Puli'));
        $this->assertSame($this->tempHome.'/Puli', $env->getHomeDirectory());
    }

    public function testDenyWebAccessToHome()
    {
        putenv('PULI_HOME='.$this->tempHome);

        $this->assertFileNotExists($this->tempHome.'/.htaccess');

        PuliEnvironment::createFromSystem();

        // Directory is protected
        $this->assertFileExists($this->tempHome.'/.htaccess');
        $this->assertSame('Deny from all', file_get_contents($this->tempHome.'/.htaccess'));
    }

    public function testGetOverwrittenHomeDirectoryOnWindows()
    {
        putenv('APPDATA=C:/path/to/home');
        putenv('PULI_HOME='.$this->tempHome.'/custom-home');

        $env = PuliEnvironment::createFromSystem();

        $this->assertTrue(is_dir($this->tempHome.'/custom-home'));
        $this->assertSame($this->tempHome.'/custom-home', $env->getHomeDirectory());
    }

    public function testGetOverwrittenHomeDirectoryOnWindowsBackslashes()
    {
        putenv('APPDATA=C:\path\to\home');
        putenv('PULI_HOME='.strtr($this->tempHome, '/', '\\').'\custom-home');

        $env = PuliEnvironment::createFromSystem();

        $this->assertTrue(is_dir($this->tempHome.'/custom-home'));
        $this->assertSame($this->tempHome.'/custom-home', $env->getHomeDirectory());
    }

    public function testFailIfNoHomeDirectoryFound()
    {
        $isWin = defined('PHP_WINDOWS_VERSION_MAJOR');

        // Mention correct variable in the exception message
        $this->setExpectedException('\Puli\PackageManager\BootstrapException', $isWin ? 'APPDATA' : ' HOME ');

        PuliEnvironment::createFromSystem();
    }

    /**
     * @expectedException \Puli\PackageManager\BootstrapException
     * @expectedExceptionMessage PULI_HOME
     */
    public function testFailIfHomeNotADirectory()
    {
        putenv('PULI_HOME='.$this->tempHome.'/some-file');

        PuliEnvironment::createFromSystem();
    }

    /**
     * @expectedException \Puli\PackageManager\BootstrapException
     * @expectedExceptionMessage HOME
     */
    public function testFailIfLinuxHomeNotADirectory()
    {
        putenv('HOME='.$this->tempHome.'/some-file');

        PuliEnvironment::createFromSystem();
    }

    /**
     * @expectedException \Puli\PackageManager\BootstrapException
     * @expectedExceptionMessage APPDATA
     */
    public function testFailIfWindowsHomeNotADirectory()
    {
        putenv('APPDATA='.$this->tempHome.'/some-file');

        PuliEnvironment::createFromSystem();
    }

    public function testReadConfigOnConstruct()
    {
        $globalConfig = new GlobalConfig();
        $globalConfig->addPluginClass(self::PLUGIN_CLASS);

        $this->configReader->expects($this->once())
            ->method('readGlobalConfig')
            ->with($this->tempHome.'/config.json')
            ->will($this->returnValue($globalConfig));

        $this->configWriter->expects($this->never())
            ->method('writeGlobalConfig');

        $env = new PuliEnvironment($this->tempHome, $this->configReader, $this->configWriter);

        $this->assertSame(array(self::PLUGIN_CLASS), $env->getGlobalPluginClasses());
    }

    public function testCreateEmptyConfigIfNotFound()
    {
        $this->configReader->expects($this->once())
            ->method('readGlobalConfig')
            ->with($this->tempHome.'/config.json')
            ->will($this->throwException(new FileNotFoundException()));

        $this->configWriter->expects($this->never())
            ->method('writeGlobalConfig');

        $env = new PuliEnvironment($this->tempHome, $this->configReader, $this->configWriter);

        $this->assertSame(array(), $env->getGlobalPluginClasses());
    }

    public function testInstallGlobalPlugin()
    {
        $this->initEnv();

        $this->assertSame(array(), $this->env->getGlobalPluginClasses());

        $this->env->installGlobalPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->env->getGlobalPluginClasses());
    }

    public function testInstallGlobalDoesNothingIfPluginExistsGlobally()
    {
        $this->initEnv();

        $this->globalConfig->addPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->env->getGlobalPluginClasses());

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

    protected function initEnv()
    {
        $this->configReader->expects($this->once())
            ->method('readGlobalConfig')
            ->with($this->tempHome.'/config.json')
            ->will($this->returnValue($this->globalConfig));

        $this->env = new PuliEnvironment(
            $this->tempHome,
            $this->configReader,
            $this->configWriter
        );
    }
}
