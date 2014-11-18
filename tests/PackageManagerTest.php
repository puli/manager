<?php

/*
 * This file is part of the Puli Packages package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Packages\Tests;

use Puli\Packages\Package\Config\PackageConfig;
use Puli\Packages\Package\Config\Reader\PackageConfigReaderInterface;
use Puli\Packages\Package\Config\RootPackageConfig;
use Puli\Packages\PackageManager;
use Puli\Packages\Repository\Config\PackageDefinition;
use Puli\Packages\Repository\Config\Reader\RepositoryConfigReaderInterface;
use Puli\Packages\Repository\Config\RepositoryConfig;

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
        $this->repositoryConfigReader = $this->getMock('Puli\Packages\Repository\Config\Reader\RepositoryConfigReaderInterface');
        $this->packageConfigReader = $this->getMock('Puli\Packages\Package\Config\Reader\PackageConfigReaderInterface');
    }

    public function testLoadRepository()
    {
        $rootConfig = new RootPackageConfig('root');
        $rootConfig->setRepositoryConfig('repository.json');
        $package1Config = new PackageConfig('package1');
        $package2Config = new PackageConfig('package2');
        $repositoryConfig = new RepositoryConfig();
        $repositoryConfig->addPackageDefinition(new PackageDefinition('relative/package1'));
        $repositoryConfig->addPackageDefinition(new PackageDefinition('/absolute/package2'));

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
        $this->assertInstanceOf('Puli\Packages\Package\RootPackage', $packages['root']);
        $this->assertSame('root', $packages['root']->getName());
        $this->assertSame($this->rootDir, $packages['root']->getInstallPath());
        $this->assertSame($rootConfig, $packages['root']->getConfig());

        $this->assertInstanceOf('Puli\Packages\Package\Package', $packages['package1']);
        $this->assertSame('package1', $packages['package1']->getName());
        $this->assertSame($this->rootDir.'/relative/package1', $packages['package1']->getInstallPath());
        $this->assertSame($package1Config, $packages['package1']->getConfig());

        $this->assertInstanceOf('Puli\Packages\Package\Package', $packages['package2']);
        $this->assertSame('package2', $packages['package2']->getName());
        $this->assertSame('/absolute/package2', $packages['package2']->getInstallPath());
        $this->assertSame($package2Config, $packages['package2']->getConfig());
    }
}
