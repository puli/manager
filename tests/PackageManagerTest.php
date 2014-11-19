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

use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\Reader\PackageConfigReaderInterface;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\PackageManager;
use Puli\PackageManager\Repository\Config\PackageDescriptor;
use Puli\PackageManager\Repository\Config\Reader\RepositoryConfigReaderInterface;
use Puli\PackageManager\Repository\Config\RepositoryConfig;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|RepositoryConfigReaderInterface
     */
    private $repositoryConfigReader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PackageConfigReaderInterface
     */
    private $packageConfigReader;

    protected function setUp()
    {
        $this->rootDir = '/root';
        $this->repositoryConfigReader = $this->getMock('Puli\PackageManager\Repository\Config\Reader\RepositoryConfigReaderInterface');
        $this->packageConfigReader = $this->getMock('Puli\PackageManager\Package\Config\Reader\PackageConfigReaderInterface');
    }

    public function testLoadRepository()
    {
        $rootConfig = new RootPackageConfig('root');
        $rootConfig->setRepositoryConfig('repository.json');
        $package1Config = new PackageConfig('package1');
        $package2Config = new PackageConfig('package2');
        $repositoryConfig = new RepositoryConfig();
        $repositoryConfig->addPackageDescriptor(new PackageDescriptor('relative/package1'));
        $repositoryConfig->addPackageDescriptor(new PackageDescriptor('/absolute/package2'));

        $this->packageConfigReader->expects($this->once())
            ->method('readRootPackageConfig')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue($rootConfig));

        $this->packageConfigReader->expects($this->at(1))
            ->method('readPackageConfig')
            ->with($this->rootDir.'/relative/package1/puli.json')
            ->will($this->returnValue($package1Config));
        $this->packageConfigReader->expects($this->at(2))
            ->method('readPackageConfig')
            ->with('/absolute/package2/puli.json')
            ->will($this->returnValue($package2Config));

        $this->repositoryConfigReader->expects($this->once())
            ->method('readRepositoryConfig')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($repositoryConfig));

        $manager = new PackageManager($this->rootDir, $this->repositoryConfigReader, $this->packageConfigReader);

        $this->assertSame($rootConfig, $manager->getRootPackageConfig());
        $this->assertSame($repositoryConfig, $manager->getRepositoryConfig());

        $packages = $manager->getPackageRepository()->getPackages();

        $this->assertCount(3, $packages);
        $this->assertInstanceOf('Puli\PackageManager\Package\RootPackage', $packages['root']);
        $this->assertSame('root', $packages['root']->getName());
        $this->assertSame($this->rootDir, $packages['root']->getInstallPath());
        $this->assertSame($rootConfig, $packages['root']->getConfig());

        $this->assertInstanceOf('Puli\PackageManager\Package\Package', $packages['package1']);
        $this->assertSame('package1', $packages['package1']->getName());
        $this->assertSame($this->rootDir.'/relative/package1', $packages['package1']->getInstallPath());
        $this->assertSame($package1Config, $packages['package1']->getConfig());

        $this->assertInstanceOf('Puli\PackageManager\Package\Package', $packages['package2']);
        $this->assertSame('package2', $packages['package2']->getName());
        $this->assertSame('/absolute/package2', $packages['package2']->getInstallPath());
        $this->assertSame($package2Config, $packages['package2']->getConfig());
    }
}
