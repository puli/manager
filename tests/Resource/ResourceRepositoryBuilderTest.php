<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Resource;

use Puli\Filesystem\Resource\LocalDirectoryResource;
use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\ResourceDescriptor;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\Config\TagDescriptor;
use Puli\PackageManager\Package\Package;
use Puli\PackageManager\Package\RootPackage;
use Puli\PackageManager\Repository\PackageRepository;
use Puli\PackageManager\Resource\ResourceRepositoryBuilder;
use Puli\Repository\ManageableRepositoryInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResourceRepositoryBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PackageRepository
     */
    private $packageRepository;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ManageableRepositoryInterface
     */
    private $repo;

    /**
     * @var ResourceRepositoryBuilder
     */
    private $builder;

    private $package1Root;

    private $package2Root;

    private $package3Root;

    protected function setUp()
    {
        $this->packageRepository = new PackageRepository();
        $this->repo = $this->getMock('Puli\Repository\ManageableRepositoryInterface');
        $this->builder = new ResourceRepositoryBuilder();
        $this->package1Root = __DIR__.'/../Fixtures/package1';
        $this->package2Root = __DIR__.'/../Fixtures/package2';
        $this->package3Root = __DIR__.'/../Fixtures/package3';
    }

    public function testIgnorePackageWithoutResources()
    {
        $config = new PackageConfig('package');

        $this->packageRepository->addPackage(new Package($config, $this->package1Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testAddResources()
    {
        $config = new PackageConfig('package');
        $config->addResourceDescriptor(new ResourceDescriptor('/package', 'resources'));
        $config->addResourceDescriptor(new ResourceDescriptor('/package/css', 'assets/css'));

        $this->packageRepository->addPackage(new Package($config, $this->package1Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package/css', new LocalDirectoryResource($this->package1Root.'/assets/css'));

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testAddResourcesFromOtherPackagesInstallPath()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/package', '@package2:resources'));

        $config2 = new PackageConfig('package2');

        $this->packageRepository->addPackage(new Package($config1, $this->package1Root));
        $this->packageRepository->addPackage(new Package($config2, $this->package2Root));

        $this->repo->expects($this->once())
            ->method('add')
            ->with('/package', new LocalDirectoryResource($this->package2Root.'/resources'));

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\PackageManager\Resource\ResourceDefinitionException
     */
    public function testFailIfReferencedPackageCouldNotBeFound()
    {
        $config = new PackageConfig('package1');
        $config->addResourceDescriptor(new ResourceDescriptor('/package', '@package2:resources'));

        $this->packageRepository->addPackage(new Package($config, $this->package1Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\Filesystem\FilesystemException
     */
    public function testFailIfResourceNotFound()
    {
        $config = new PackageConfig('package1');
        $config->addResourceDescriptor(new ResourceDescriptor('/package', 'foobar'));

        $this->packageRepository->addPackage(new Package($config, $this->package1Root));

        $this->builder->loadPackages($this->packageRepository);
    }

    public function testIgnoreResourceOrder()
    {
        $config = new PackageConfig('package');
        $config->addResourceDescriptor(new ResourceDescriptor('/package/css', 'assets/css'));
        $config->addResourceDescriptor(new ResourceDescriptor('/package', 'resources'));

        $this->packageRepository->addPackage(new Package($config, $this->package1Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package/css', new LocalDirectoryResource($this->package1Root.'/assets/css'));

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testExportResourceWithMultipleLocalPaths()
    {
        $config = new PackageConfig('package');
        $config->addResourceDescriptor(new ResourceDescriptor('/package', array('resources', 'assets')));

        $this->packageRepository->addPackage(new Package($config, $this->package1Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package', new LocalDirectoryResource($this->package1Root.'/assets'));

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testOverrideExistingPackage()
    {
        $overridden = new PackageConfig('package1');
        $overridden->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));
        $overridden->addResourceDescriptor(new ResourceDescriptor('/package1/css', 'assets/css'));

        $overrider = new PackageConfig('package2');
        $overrider->addResourceDescriptor(new ResourceDescriptor('/package1', 'override'));
        $overrider->addResourceDescriptor(new ResourceDescriptor('/package1/css', 'css-override'));
        $overrider->setOverriddenPackages('package1');

        // Add overridden package first
        $this->packageRepository->addPackage(new Package($overridden, $this->package1Root));
        $this->packageRepository->addPackage(new Package($overrider, $this->package2Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1/css', new LocalDirectoryResource($this->package1Root.'/assets/css'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package2Root.'/override'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/package1/css', new LocalDirectoryResource($this->package2Root.'/css-override'));

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testOverrideFuturePackage()
    {
        $overridden = new PackageConfig('package1');
        $overridden->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));
        $overridden->addResourceDescriptor(new ResourceDescriptor('/package1/css', 'assets/css'));

        $overrider = new PackageConfig('package2');
        $overrider->addResourceDescriptor(new ResourceDescriptor('/package1', 'override'));
        $overrider->addResourceDescriptor(new ResourceDescriptor('/package1/css', 'css-override'));
        $overrider->setOverriddenPackages('package1');

        // Add overriding package first
        $this->packageRepository->addPackage(new Package($overrider, $this->package2Root));
        $this->packageRepository->addPackage(new Package($overridden, $this->package1Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1/css', new LocalDirectoryResource($this->package1Root.'/assets/css'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package2Root.'/override'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/package1/css', new LocalDirectoryResource($this->package2Root.'/css-override'));

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testOverrideChain()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));

        $config2 = new PackageConfig('package2');
        $config2->addResourceDescriptor(new ResourceDescriptor('/package1', 'override'));
        $config2->setOverriddenPackages('package1');

        $config3 = new PackageConfig('package3');
        $config3->addResourceDescriptor(new ResourceDescriptor('/package1', 'override2'));
        $config3->setOverriddenPackages('package2');

        $this->packageRepository->addPackage(new Package($config1, $this->package1Root));
        $this->packageRepository->addPackage(new Package($config2, $this->package2Root));
        $this->packageRepository->addPackage(new Package($config3, $this->package3Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package2Root.'/override'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package3Root.'/override2'));

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testOverrideMultiplePackages()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));

        $config2 = new PackageConfig('package2');
        $config2->addResourceDescriptor(new ResourceDescriptor('/package2', 'resources'));

        $config3 = new PackageConfig('package3');
        $config3->addResourceDescriptor(new ResourceDescriptor('/package1', 'override1'));
        $config3->addResourceDescriptor(new ResourceDescriptor('/package2', 'override2'));
        $config3->setOverriddenPackages(array('package1', 'package2'));

        $this->packageRepository->addPackage(new Package($config1, $this->package1Root));
        $this->packageRepository->addPackage(new Package($config2, $this->package2Root));
        $this->packageRepository->addPackage(new Package($config3, $this->package3Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package2', new LocalDirectoryResource($this->package2Root.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package3Root.'/override1'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/package2', new LocalDirectoryResource($this->package3Root.'/override2'));

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testOverrideIgnoredIfPackageNotFound()
    {
        $config = new PackageConfig('package');
        $config->addResourceDescriptor(new ResourceDescriptor('/package', 'resources'));
        $config->setOverriddenPackages('foobar');

        $this->packageRepository->addPackage(new Package($config, $this->package1Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testOverrideWithMultipleDirectories()
    {
        $overridden = new PackageConfig('package1');
        $overridden->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));

        $overrider = new PackageConfig('package2');
        $overrider->addResourceDescriptor(new ResourceDescriptor('/package1', array('override', 'css-override')));
        $overrider->setOverriddenPackages('package1');

        $this->packageRepository->addPackage(new Package($overridden, $this->package1Root));
        $this->packageRepository->addPackage(new Package($overrider, $this->package2Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package2Root.'/override'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package2Root.'/css-override'));

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\PackageManager\Resource\ResourceConflictException
     */
    public function testConflictIfSamePathsButNoOverrideStatement()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/path', 'resources'));

        $config2 = new PackageConfig('package2');
        $config2->addResourceDescriptor(new ResourceDescriptor('/path', 'resources'));

        $this->packageRepository->addPackage(new Package($config1, $this->package1Root));
        $this->packageRepository->addPackage(new Package($config2, $this->package2Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\PackageManager\Resource\ResourceConflictException
     */
    public function testConflictIfExistingSubPathAndNoOverrideStatement()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/path', 'resources'));

        $config2 = new PackageConfig('package2');
        $config2->addResourceDescriptor(new ResourceDescriptor('/path/config', 'override'));

        $this->packageRepository->addPackage(new Package($config1, $this->package1Root));
        $this->packageRepository->addPackage(new Package($config2, $this->package2Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testNoConflictIfNewSubPathAndNoOverrideStatement()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/path', 'resources'));

        $config2 = new PackageConfig('package2');
        $config2->addResourceDescriptor(new ResourceDescriptor('/path/new', 'override'));

        $this->packageRepository->addPackage(new Package($config1, $this->package1Root));
        $this->packageRepository->addPackage(new Package($config2, $this->package2Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path/new', new LocalDirectoryResource($this->package2Root.'/override'));

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testDefinePackageOrderOnRootPackage()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/path', 'resources'));

        $config2 = new PackageConfig('package2');
        $config2->addResourceDescriptor(new ResourceDescriptor('/path', 'override'));

        $globalConfig = new GlobalConfig();
        $rootConfig = new RootPackageConfig($globalConfig, 'root');
        $rootConfig->setPackageOrder(array('package1', 'package2'));

        $this->packageRepository->addPackage(new Package($config1, $this->package1Root));
        $this->packageRepository->addPackage(new Package($config2, $this->package2Root));
        $this->packageRepository->addPackage(new RootPackage($rootConfig, $this->package3Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new LocalDirectoryResource($this->package2Root.'/override'));

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\PackageManager\Resource\ResourceConflictException
     */
    public function testPackageOrderInNonRootPackageIsIgnored()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/path', 'resources'));

        $config2 = new PackageConfig('package2');
        $config2->addResourceDescriptor(new ResourceDescriptor('/path', 'override'));

        $globalConfig = new GlobalConfig();
        $pseudoRootConfig = new RootPackageConfig($globalConfig, 'root');
        $pseudoRootConfig->setPackageOrder(array('package1', 'package2'));

        $this->packageRepository->addPackage(new Package($config1, $this->package1Root));
        $this->packageRepository->addPackage(new Package($config2, $this->package2Root));
        $this->packageRepository->addPackage(new Package($pseudoRootConfig, $this->package3Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testTagResources()
    {
        $config = new PackageConfig('package1');
        $config->addResourceDescriptor(new ResourceDescriptor('/package', 'resources'));
        $config->addTagDescriptor(new TagDescriptor('/package', 'tag'));

        $this->packageRepository->addPackage(new Package($config, $this->package1Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/package', 'tag');

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testTagResourcesFromExistingOtherPackage()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));

        $config2 = new PackageConfig('package2');
        $config2->addTagDescriptor(new TagDescriptor('/package1', 'tag'));

        $this->packageRepository->addPackage(new Package($config1, $this->package1Root));
        $this->packageRepository->addPackage(new Package($config2, $this->package2Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/package1', 'tag');

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testTagResourcesFromFutureOtherPackage()
    {
        $config1 = new PackageConfig('package1');
        $config1->addTagDescriptor(new TagDescriptor('/package2', 'tag'));

        $config2 = new PackageConfig('package2');
        $config2->addResourceDescriptor(new ResourceDescriptor('/package2', 'resources'));

        $this->packageRepository->addPackage(new Package($config1, $this->package1Root));
        $this->packageRepository->addPackage(new Package($config2, $this->package2Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package2', new LocalDirectoryResource($this->package2Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/package2', 'tag');

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testTagInTwoPackages()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));
        $config1->addTagDescriptor(new TagDescriptor('/package1', 'tag1'));

        $config2 = new PackageConfig('package2');
        $config2->addTagDescriptor(new TagDescriptor('/package1', 'tag2'));

        $this->packageRepository->addPackage(new Package($config1, $this->package1Root));
        $this->packageRepository->addPackage(new Package($config2, $this->package2Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/package1', 'tag1');

        $this->repo->expects($this->at(2))
            ->method('tag')
            ->with('/package1', 'tag2');

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testDuplicateTags()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));
        $config1->addTagDescriptor(new TagDescriptor('/package1', 'tag'));

        $config2 = new PackageConfig('package2');
        $config2->addTagDescriptor(new TagDescriptor('/package1', 'tag'));

        $this->packageRepository->addPackage(new Package($config1, $this->package1Root));
        $this->packageRepository->addPackage(new Package($config2, $this->package2Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/package1', 'tag');

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testMultipleTags()
    {
        $config = new PackageConfig('package1');
        $config->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));
        $config->addTagDescriptor(new TagDescriptor('/package1', array('tag1', 'tag2')));

        $this->packageRepository->addPackage(new Package($config, $this->package1Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/package1', 'tag1');

        $this->repo->expects($this->at(2))
            ->method('tag')
            ->with('/package1', 'tag2');

        $this->builder->loadPackages($this->packageRepository);
        $this->builder->buildRepository($this->repo);
    }

    public function testBuildRepositoryDoesNothingIfNotLoaded()
    {
        $this->repo->expects($this->never())
            ->method('add');
        $this->repo->expects($this->never())
            ->method('tag');

        $this->builder->buildRepository($this->repo);
    }
}
