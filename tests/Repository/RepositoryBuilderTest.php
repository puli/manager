<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Repository;

use Puli\Repository\Filesystem\Resource\LocalDirectoryResource;
use Puli\Repository\ManageableRepository;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\ResourceMapping;
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
     * @var \PHPUnit_Framework_MockObject_MockObject|ManageableRepository
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
        $this->repo = $this->getMock('Puli\Repository\ManageableRepository');
        $this->builder = new RepositoryBuilder();
        $this->package1Root = __DIR__.'/Fixtures/package1';
        $this->package2Root = __DIR__.'/Fixtures/package2';
        $this->package3Root = __DIR__.'/Fixtures/package3';
    }

    public function testIgnorePackageWithoutResources()
    {
        $packageFile = new PackageFile('package');

        $this->packageCollection->add(new Package($packageFile, $this->package1Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testAddResources()
    {
        $packageFile = new PackageFile('package');
        $packageFile->addResourceMapping(new ResourceMapping('/package', 'resources'));
        $packageFile->addResourceMapping(new ResourceMapping('/package/css', 'assets/css'));

        $this->packageCollection->add(new Package($packageFile, $this->package1Root));

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
        $packageFile1 = new PackageFile('package1');
        $packageFile1->addResourceMapping(new ResourceMapping('/package', '@package2:resources'));

        $packageFile2 = new PackageFile('package2');

        $this->packageCollection->add(new Package($packageFile1, $this->package1Root));
        $this->packageCollection->add(new Package($packageFile2, $this->package2Root));

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
        $packageFile = new PackageFile('package1');
        $packageFile->addResourceMapping(new ResourceMapping('/package', '@package2:resources'));

        $this->packageCollection->add(new Package($packageFile, $this->package1Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testDoNotFailIfReferencedOptionalPackageCouldNotBeFound()
    {
        $packageFile = new PackageFile('package1');
        $packageFile->addResourceMapping(new ResourceMapping('/package', '@?package2:resources'));

        $this->packageCollection->add(new Package($packageFile, $this->package1Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    /**
     * @expectedException \Puli\Repository\Filesystem\FilesystemException
     */
    public function testFailIfResourceNotFound()
    {
        $packageFile = new PackageFile('package1');
        $packageFile->addResourceMapping(new ResourceMapping('/package', 'foobar'));

        $this->packageCollection->add(new Package($packageFile, $this->package1Root));

        $this->builder->loadPackages($this->packageCollection);
    }

    public function testIgnoreResourceOrder()
    {
        $packageFile = new PackageFile('package');
        $packageFile->addResourceMapping(new ResourceMapping('/package/css', 'assets/css'));
        $packageFile->addResourceMapping(new ResourceMapping('/package', 'resources'));

        $this->packageCollection->add(new Package($packageFile, $this->package1Root));

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
        $packageFile = new PackageFile('package');
        $packageFile->addResourceMapping(new ResourceMapping('/package', array('resources', 'assets')));

        $this->packageCollection->add(new Package($packageFile, $this->package1Root));

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
        $overridden = new PackageFile('package1');
        $overridden->addResourceMapping(new ResourceMapping('/package1', 'resources'));
        $overridden->addResourceMapping(new ResourceMapping('/package1/css', 'assets/css'));

        $overrider = new PackageFile('package2');
        $overrider->addResourceMapping(new ResourceMapping('/package1', 'override'));
        $overrider->addResourceMapping(new ResourceMapping('/package1/css', 'css-override'));
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
        $overridden = new PackageFile('package1');
        $overridden->addResourceMapping(new ResourceMapping('/package1', 'resources'));
        $overridden->addResourceMapping(new ResourceMapping('/package1/css', 'assets/css'));

        $overrider = new PackageFile('package2');
        $overrider->addResourceMapping(new ResourceMapping('/package1', 'override'));
        $overrider->addResourceMapping(new ResourceMapping('/package1/css', 'css-override'));
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
        $packageFile1 = new PackageFile('package1');
        $packageFile1->addResourceMapping(new ResourceMapping('/package1', 'resources'));

        $packageFile2 = new PackageFile('package2');
        $packageFile2->addResourceMapping(new ResourceMapping('/package1', 'override'));
        $packageFile2->setOverriddenPackages('package1');

        $packageFile3 = new PackageFile('package3');
        $packageFile3->addResourceMapping(new ResourceMapping('/package1', 'override2'));
        $packageFile3->setOverriddenPackages('package2');

        $this->packageCollection->add(new Package($packageFile1, $this->package1Root));
        $this->packageCollection->add(new Package($packageFile2, $this->package2Root));
        $this->packageCollection->add(new Package($packageFile3, $this->package3Root));

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
        $packageFile1 = new PackageFile('package1');
        $packageFile1->addResourceMapping(new ResourceMapping('/package1', 'resources'));

        $packageFile2 = new PackageFile('package2');
        $packageFile2->addResourceMapping(new ResourceMapping('/package2', 'resources'));

        $packageFile3 = new PackageFile('package3');
        $packageFile3->addResourceMapping(new ResourceMapping('/package1', 'override1'));
        $packageFile3->addResourceMapping(new ResourceMapping('/package2', 'override2'));
        $packageFile3->setOverriddenPackages(array('package1', 'package2'));

        $this->packageCollection->add(new Package($packageFile1, $this->package1Root));
        $this->packageCollection->add(new Package($packageFile2, $this->package2Root));
        $this->packageCollection->add(new Package($packageFile3, $this->package3Root));

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
        $packageFile = new PackageFile('package');
        $packageFile->addResourceMapping(new ResourceMapping('/package', 'resources'));
        $packageFile->setOverriddenPackages('foobar');

        $this->packageCollection->add(new Package($packageFile, $this->package1Root));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package', new LocalDirectoryResource($this->package1Root.'/resources'));

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testOverrideWithMultipleDirectories()
    {
        $overridden = new PackageFile('package1');
        $overridden->addResourceMapping(new ResourceMapping('/package1', 'resources'));

        $overrider = new PackageFile('package2');
        $overrider->addResourceMapping(new ResourceMapping('/package1', array('override', 'css-override')));
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
        $packageFile1 = new PackageFile('package1');
        $packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));

        $packageFile2 = new PackageFile('package2');
        $packageFile2->addResourceMapping(new ResourceMapping('/path', 'resources'));

        $this->packageCollection->add(new Package($packageFile1, $this->package1Root));
        $this->packageCollection->add(new Package($packageFile2, $this->package2Root));

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
        $packageFile1 = new PackageFile('package1');
        $packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));

        $packageFile2 = new PackageFile('package2');
        $packageFile2->addResourceMapping(new ResourceMapping('/path/config', 'override'));

        $this->packageCollection->add(new Package($packageFile1, $this->package1Root));
        $this->packageCollection->add(new Package($packageFile2, $this->package2Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testNoConflictIfNewSubPathAndNoOverrideStatement()
    {
        $packageFile1 = new PackageFile('package1');
        $packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));

        $packageFile2 = new PackageFile('package2');
        $packageFile2->addResourceMapping(new ResourceMapping('/path/new', 'override'));

        $this->packageCollection->add(new Package($packageFile1, $this->package1Root));
        $this->packageCollection->add(new Package($packageFile2, $this->package2Root));

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
        $packageFile1 = new PackageFile('package1');
        $packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));

        $packageFile2 = new PackageFile('package2');
        $packageFile2->addResourceMapping(new ResourceMapping('/path', 'override'));

        $rootConfig = new RootPackageFile('root');
        $rootConfig->setPackageOrder(array('package1', 'package2'));

        $this->packageCollection->add(new Package($packageFile1, $this->package1Root));
        $this->packageCollection->add(new Package($packageFile2, $this->package2Root));
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
        $packageFile1 = new PackageFile('package1');
        $packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));

        $packageFile2 = new PackageFile('package2');
        $packageFile2->addResourceMapping(new ResourceMapping('/path', 'override'));

        $pseudoRootConfig = new RootPackageFile('root');
        $pseudoRootConfig->setPackageOrder(array('package1', 'package2'));

        $this->packageCollection->add(new Package($packageFile1, $this->package1Root));
        $this->packageCollection->add(new Package($packageFile2, $this->package2Root));
        $this->packageCollection->add(new Package($pseudoRootConfig, $this->package3Root));

        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->loadPackages($this->packageCollection);
        $this->builder->buildRepository($this->repo);
    }

    public function testBuildRepositoryDoesNothingIfNotLoaded()
    {
        $this->repo->expects($this->never())
            ->method('add');

        $this->builder->buildRepository($this->repo);
    }
}
