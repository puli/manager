<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Repository;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\Package;
use Puli\PackageManager\Package\RootPackage;
use Puli\PackageManager\Repository\PackageRepository;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageRepositoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PackageRepository
     */
    private $repository;

    protected function setUp()
    {
        $this->repository = new PackageRepository();
    }

    public function testGetPackage()
    {
        $config = new PackageConfig('package');
        $package = new Package($config, '/path');

        $this->repository->addPackage($package);

        $this->assertSame($package, $this->repository->getPackage('package'));
    }

    /**
     * @expectedException \Puli\PackageManager\Repository\NoSuchPackageException
     */
    public function testGetPackageFailsIfNotFound()
    {
        $this->repository->getPackage('package');
    }

    public function testGetRootPackageReturnsNull()
    {
        $this->assertNull($this->repository->getRootPackage());
    }

    public function testGetRootPackageReturnsAddedRootPackage()
    {
        $config1 = new PackageConfig('package1');
        $package1 = new Package($config1, '/path1');

        $config2 = new PackageConfig('package2');
        $package2 = new Package($config2, '/path2');

        $globalConfig = new GlobalConfig();
        $rootConfig = new RootPackageConfig($globalConfig, 'root');
        $rootPackage = new RootPackage($rootConfig, '/path3');

        $this->repository->addPackage($package1);
        $this->repository->addPackage($rootPackage);
        $this->repository->addPackage($package2);

        $this->assertSame($rootPackage, $this->repository->getRootPackage());
    }
}
