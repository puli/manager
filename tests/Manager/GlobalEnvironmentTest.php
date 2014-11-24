<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Manager;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Config\GlobalConfigStorage;
use Puli\PackageManager\Manager\GlobalEnvironment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GlobalEnvironmentTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $homeDir;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|GlobalConfigStorage
     */
    private $globalConfigStorage;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    protected function setUp()
    {
        $this->homeDir = __DIR__.'/Fixtures/home';
        $this->globalConfigStorage = $this->getMockBuilder('Puli\PackageManager\Config\GlobalConfigStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
    }

    public function testCreate()
    {
        $globalConfig = new GlobalConfig();

        $this->globalConfigStorage->expects($this->once())
            ->method('loadGlobalConfig')
            ->with($this->homeDir.'/config.json')
            ->will($this->returnValue($globalConfig));

        $environment = new GlobalEnvironment(
            $this->homeDir,
            $this->globalConfigStorage,
            $this->dispatcher
        );

        $this->assertSame($this->homeDir, $environment->getHomeDirectory());
        $this->assertSame($globalConfig, $environment->getGlobalConfig());
        $this->assertSame($this->dispatcher, $environment->getEventDispatcher());
    }

    /**
     * @expectedException \Puli\PackageManager\FileNotFoundException
     * @expectedExceptionMessage /foobar
     */
    public function testFailIfNonExistingHomeDir()
    {
        new GlobalEnvironment(
            __DIR__.'/foobar',
            $this->globalConfigStorage,
            $this->dispatcher
        );
    }

    /**
     * @expectedException \Puli\PackageManager\NoDirectoryException
     * @expectedExceptionMessage /file
     */
    public function testFailIfHomeDirNoDirectory()
    {
        new GlobalEnvironment(
            $this->homeDir.'/file',
            $this->globalConfigStorage,
            $this->dispatcher
        );
    }
}
