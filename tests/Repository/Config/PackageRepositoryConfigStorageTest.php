<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Repository\Config;

use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfigStorage;
use Puli\PackageManager\Repository\Config\Reader\RepositoryConfigReaderInterface;
use Puli\PackageManager\Repository\Config\Writer\RepositoryConfigWriterInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageRepositoryConfigStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PackageRepositoryConfigStorage
     */
    private $storage;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|RepositoryConfigReaderInterface
     */
    private $repositoryConfigReader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|RepositoryConfigWriterInterface
     */
    private $repositoryConfigWriter;

    protected function setUp()
    {
        $this->repositoryConfigReader = $this->getMock('Puli\PackageManager\Repository\Config\Reader\RepositoryConfigReaderInterface');
        $this->repositoryConfigWriter = $this->getMock('Puli\PackageManager\Repository\Config\Writer\RepositoryConfigWriterInterface');

        $this->storage = new PackageRepositoryConfigStorage(
            $this->repositoryConfigReader,
            $this->repositoryConfigWriter
        );
    }

    public function testLoadRepositoryConfig()
    {
        $config = new PackageRepositoryConfig();

        $this->repositoryConfigReader->expects($this->once())
            ->method('readRepositoryConfig')
            ->with('/path')
            ->will($this->returnValue($config));

        $this->assertSame($config, $this->storage->loadRepositoryConfig('/path'));
    }

    public function testLoadRepositoryConfigCreatesNewIfNotFound()
    {
        $this->repositoryConfigReader->expects($this->once())
            ->method('readRepositoryConfig')
            ->with('/path')
            ->will($this->throwException(new FileNotFoundException()));

        $this->assertEquals(new PackageRepositoryConfig('/path'), $this->storage->loadRepositoryConfig('/path'));
    }

    public function testSaveRepositoryConfig()
    {
        $config = new PackageRepositoryConfig('/path');

        $this->repositoryConfigWriter->expects($this->once())
            ->method('writeRepositoryConfig')
            ->with($config, '/path');

        $this->storage->saveRepositoryConfig($config);
    }
}
