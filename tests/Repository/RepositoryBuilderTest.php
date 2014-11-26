<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Repository;

use Puli\Filesystem\Resource\LocalDirectoryResource;
use Puli\Repository\ManageableRepositoryInterface;
use Puli\RepositoryManager\Config\GlobalConfig;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\Config\PackageConfig;
use Puli\RepositoryManager\Package\Config\ResourceDescriptor;
use Puli\RepositoryManager\Package\Config\RootPackageConfig;
use Puli\RepositoryManager\Package\Config\TagDescriptor;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\RootPackage;
use Puli\RepositoryManager\Repository\RepositoryBuilder;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PackageCollection
     */
    private $packageCollection;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ManageableRepositoryInterface
     */
    private $repo;

    /**
     * @var RepositoryBuilder
     */
    private $builder;

    private $package1Root;

    private $package2Root;

    private $package3Root;

    protected function setUp()
    {
        $this->packageCollection = new PackageCollection();
        $this->repo = $this->getMock('Puli\Repository\ManageableRepositoryInterface');
        $this->builder = new RepositoryBuilder();
        $this->package1Root = __DIR__.'/Fixtures/package1';
        $this->package2Root = __DIR__.'/Fixtures/package2';
        $this->package3Root = __DIR__.'/Fixtures/package3';
    }

    public function testIgnorePackageWithoutResources()
    {
        $config = new PackageConfig('package');

        $this->packageCollection->add(new Package($config, $this->package1Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testAddResources()
    {
        $config = new PackageConfig('package');
        $config->addResourceDescriptor(new ResourceDescriptor('/package', 'resources'));
        $config->addResourceDescriptor(new ResourceDescriptor('/package/css', 'assets/css'));

        $this->packageCollection->add(new Package($config, $this->package1Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package/css', new LocalDirectoryResource($this->package1Root.'/assets/css'));

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testAddResourcesFromOtherPackagesInstallPath()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/package', '@package2:resources'));

        $config2 = new PackageConfig('package2');

        $this->packageCollection->add(new Package($config1, $this->package1Root));
        $this->packageCollection->add(new Package($config2, $this->package2Root));

        $this->repo->expects($this->once())
            ->method('add')
            ->with('/package', new LocalDirectoryResource($this->package2Root.'/resources'));

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\RepositoryManager\Repository\ResourceDefinitionException
     */
    public function testFailIfReferencedPackageCouldNotBeFound()
    {
        $config = new PackageConfig('package1');
        $config->addResourceDescriptor(new ResourceDescriptor('/package', '@package2:resources'));

        $this->packageCollection->add(new Package($config, $this->package1Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testDoNotFailIfReferencedOptionalPackageCouldNotBeFound()
    {
        $config = new PackageConfig('package1');
        $config->addResourceDescriptor(new ResourceDescriptor('/package', '@?package2:resources'));

        $this->packageCollection->add(new Package($config, $this->package1Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\Filesystem\FilesystemException
     */
    public function testFailIfResourceNotFound()
    {
        $config = new PackageConfig('package1');
        $config->addResourceDescriptor(new ResourceDescriptor('/package', 'foobar'));

        $this->packageCollection->add(new Package($config, $this->package1Root));

        $this->builder->loadPackages($this->packageCollection);
    }

    public function testIgnoreResourceOrder()
    {
        $config = new PackageConfig('package');
        $config->addResourceDescriptor(new ResourceDescriptor('/package/css', 'assets/css'));
        $config->addResourceDescriptor(new ResourceDescriptor('/package', 'resources'));

        $this->packageCollection->add(new Package($config, $this->package1Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package/css', new LocalDirectoryResource($this->package1Root.'/assets/css'));

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testExportResourceWithMultipleLocalPaths()
    {
        $config = new PackageConfig('package');
        $config->addResourceDescriptor(new ResourceDescriptor('/package', array('resources', 'assets')));

        $this->packageCollection->add(new Package($config, $this->package1Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package', new LocalDirectoryResource($this->package1Root.'/assets'));

        $this->builder->loadPackages($this->packageCollection);
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
        $this->packageCollection->add(new Package($overridden, $this->package1Root));
        $this->packageCollection->add(new Package($overrider, $this->package2Root));

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

        $this->builder->loadPackages($this->packageCollection);
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
        $this->packageCollection->add(new Package($overrider, $this->package2Root));
        $this->packageCollection->add(new Package($overridden, $this->package1Root));

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

        $this->builder->loadPackages($this->packageCollection);
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

        $this->packageCollection->add(new Package($config1, $this->package1Root));
        $this->packageCollection->add(new Package($config2, $this->package2Root));
        $this->packageCollection->add(new Package($config3, $this->package3Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package2Root.'/override'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package3Root.'/override2'));

        $this->builder->loadPackages($this->packageCollection);
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

        $this->packageCollection->add(new Package($config1, $this->package1Root));
        $this->packageCollection->add(new Package($config2, $this->package2Root));
        $this->packageCollection->add(new Package($config3, $this->package3Root));

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

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testOverrideIgnoredIfPackageNotFound()
    {
        $config = new PackageConfig('package');
        $config->addResourceDescriptor(new ResourceDescriptor('/package', 'resources'));
        $config->setOverriddenPackages('foobar');

        $this->packageCollection->add(new Package($config, $this->package1Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testOverrideWithMultipleDirectories()
    {
        $overridden = new PackageConfig('package1');
        $overridden->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));

        $overrider = new PackageConfig('package2');
        $overrider->addResourceDescriptor(new ResourceDescriptor('/package1', array('override', 'css-override')));
        $overrider->setOverriddenPackages('package1');

        $this->packageCollection->add(new Package($overridden, $this->package1Root));
        $this->packageCollection->add(new Package($overrider, $this->package2Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package2Root.'/override'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package2Root.'/css-override'));

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\RepositoryManager\Repository\ResourceConflictException
     */
    public function testConflictIfSamePathsButNoOverrideStatement()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/path', 'resources'));

        $config2 = new PackageConfig('package2');
        $config2->addResourceDescriptor(new ResourceDescriptor('/path', 'resources'));

        $this->packageCollection->add(new Package($config1, $this->package1Root));
        $this->packageCollection->add(new Package($config2, $this->package2Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\RepositoryManager\Repository\ResourceConflictException
     */
    public function testConflictIfExistingSubPathAndNoOverrideStatement()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/path', 'resources'));

        $config2 = new PackageConfig('package2');
        $config2->addResourceDescriptor(new ResourceDescriptor('/path/config', 'override'));

        $this->packageCollection->add(new Package($config1, $this->package1Root));
        $this->packageCollection->add(new Package($config2, $this->package2Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testNoConflictIfNewSubPathAndNoOverrideStatement()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/path', 'resources'));

        $config2 = new PackageConfig('package2');
        $config2->addResourceDescriptor(new ResourceDescriptor('/path/new', 'override'));

        $this->packageCollection->add(new Package($config1, $this->package1Root));
        $this->packageCollection->add(new Package($config2, $this->package2Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path/new', new LocalDirectoryResource($this->package2Root.'/override'));

        $this->builder->loadPackages($this->packageCollection);
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

        $this->packageCollection->add(new Package($config1, $this->package1Root));
        $this->packageCollection->add(new Package($config2, $this->package2Root));
        $this->packageCollection->add(new RootPackage($rootConfig, $this->package3Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new LocalDirectoryResource($this->package2Root.'/override'));

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\RepositoryManager\Repository\ResourceConflictException
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

        $this->packageCollection->add(new Package($config1, $this->package1Root));
        $this->packageCollection->add(new Package($config2, $this->package2Root));
        $this->packageCollection->add(new Package($pseudoRootConfig, $this->package3Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testTagResources()
    {
        $config = new PackageConfig('package1');
        $config->addResourceDescriptor(new ResourceDescriptor('/package', 'resources'));
        $config->addTagDescriptor(new TagDescriptor('/package', 'tag'));

        $this->packageCollection->add(new Package($config, $this->package1Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/package', 'tag');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testTagResourcesFromExistingOtherPackage()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));

        $config2 = new PackageConfig('package2');
        $config2->addTagDescriptor(new TagDescriptor('/package1', 'tag'));

        $this->packageCollection->add(new Package($config1, $this->package1Root));
        $this->packageCollection->add(new Package($config2, $this->package2Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/package1', 'tag');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testTagResourcesFromFutureOtherPackage()
    {
        $config1 = new PackageConfig('package1');
        $config1->addTagDescriptor(new TagDescriptor('/package2', 'tag'));

        $config2 = new PackageConfig('package2');
        $config2->addResourceDescriptor(new ResourceDescriptor('/package2', 'resources'));

        $this->packageCollection->add(new Package($config1, $this->package1Root));
        $this->packageCollection->add(new Package($config2, $this->package2Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package2', new LocalDirectoryResource($this->package2Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/package2', 'tag');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testTagInTwoPackages()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));
        $config1->addTagDescriptor(new TagDescriptor('/package1', 'tag1'));

        $config2 = new PackageConfig('package2');
        $config2->addTagDescriptor(new TagDescriptor('/package1', 'tag2'));

        $this->packageCollection->add(new Package($config1, $this->package1Root));
        $this->packageCollection->add(new Package($config2, $this->package2Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/package1', 'tag1');

        $this->repo->expects($this->at(2))
            ->method('tag')
            ->with('/package1', 'tag2');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testDuplicateTags()
    {
        $config1 = new PackageConfig('package1');
        $config1->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));
        $config1->addTagDescriptor(new TagDescriptor('/package1', 'tag'));

        $config2 = new PackageConfig('package2');
        $config2->addTagDescriptor(new TagDescriptor('/package1', 'tag'));

        $this->packageCollection->add(new Package($config1, $this->package1Root));
        $this->packageCollection->add(new Package($config2, $this->package2Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/package1', 'tag');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testMultipleTags()
    {
        $config = new PackageConfig('package1');
        $config->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));
        $config->addTagDescriptor(new TagDescriptor('/package1', array('tag1', 'tag2')));

        $this->packageCollection->add(new Package($config, $this->package1Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('tag')
            ->with('/package1', 'tag1');

        $this->repo->expects($this->at(2))
            ->method('tag')
            ->with('/package1', 'tag2');

        $this->builder->loadPackages($this->packageCollection);
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
