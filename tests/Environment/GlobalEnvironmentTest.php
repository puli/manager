<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Environment;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFile;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFileStorage;
use Puli\RepositoryManager\Environment\GlobalEnvironment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GlobalEnvironmentTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $homeDir;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ConfigFileStorage
     */
    private $configFileStorage;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    protected function setUp()
    {
        $this->homeDir = __DIR__.'/Fixtures/home';
        $this->configFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Config\ConfigFile\ConfigFileStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
    }

    public function testCreate()
    {
        $this->configFileStorage->expects($this->once())
            ->method('loadConfigFile')
            ->with($this->homeDir.'/config.json')
            ->will($this->returnCallback(function ($path, Config $baseConfig = null) {
                return new ConfigFile($path, $baseConfig);
            }));

        $environment = new GlobalEnvironment(
            $this->homeDir,
            $this->configFileStorage,
            $this->dispatcher
        );

        $this->assertSame($this->homeDir, $environment->getHomeDirectory());
        $this->assertInstanceOf('Puli\RepositoryManager\Config\EnvConfig', $environment->getConfig());
        $this->assertInstanceOf('Puli\RepositoryManager\Config\ConfigFile\ConfigFile', $environment->getConfigFile());
        $this->assertSame($this->dispatcher, $environment->getEventDispatcher());

        // should be loaded from DefaultConfig
        $this->assertSame('.puli', $environment->getConfig()->get(Config::PULI_DIR));
    }

    public function testCreateWithoutHomeDir()
    {
        $this->configFileStorage->expects($this->never())
            ->method('loadConfigFile');

        $environment = new GlobalEnvironment(
            null,
            $this->configFileStorage,
            $this->dispatcher
        );

        $this->assertNull($environment->getHomeDirectory());
        $this->assertInstanceOf('Puli\RepositoryManager\Config\EnvConfig', $environment->getConfig());
        $this->assertNull($environment->getConfigFile());
        $this->assertSame($this->dispatcher, $environment->getEventDispatcher());

        // should be loaded from DefaultConfig
        $this->assertSame('.puli', $environment->getConfig()->get(Config::PULI_DIR));
    }

    /**
     * @expectedException \Puli\RepositoryManager\FileNotFoundException
     * @expectedExceptionMessage /foobar
     */
    public function testFailIfNonExistingHomeDir()
    {
        new GlobalEnvironment(
            __DIR__.'/foobar',
            $this->configFileStorage,
            $this->dispatcher
        );
    }

    /**
     * @expectedException \Puli\RepositoryManager\NoDirectoryException
     * @expectedExceptionMessage /file
     */
    public function testFailIfHomeDirNoDirectory()
    {
        new GlobalEnvironment(
            $this->homeDir.'/file',
            $this->configFileStorage,
            $this->dispatcher
        );
    }
}
