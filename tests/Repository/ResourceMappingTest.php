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

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\Package\NoSuchPackageException;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageCollection;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Repository\ResourceMapping;
use Puli\RepositoryManager\Repository\RepositoryPathConflict;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResourceMappingTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $packageDir1;

    /**
     * @var string
     */
    private $packageDir2;

    /**
     * @var string
     */
    private $packageDir3;

    /**
     * @var Package
     */
    private $package1;

    /**
     * @var Package
     */
    private $package2;

    /**
     * @var Package
     */
    private $package3;

    /**
     * @var PackageCollection
     */
    private $packages;

    protected function setUp()
    {
        $this->packageDir1 = __DIR__.'/Fixtures/package1';
        $this->packageDir2 = __DIR__.'/Fixtures/package2';
        $this->packageDir3 = __DIR__.'/Fixtures/package3';
        $this->package1 = new Package(new PackageFile('vendor/package1'), $this->packageDir1);
        $this->package2 = new Package(new PackageFile('vendor/package2'), $this->packageDir2);
        $this->package3 = new Package(new PackageFile('vendor/package3'), $this->packageDir3);
        $this->packages = new PackageCollection(array(
            $this->package1,
            $this->package2,
            $this->package3,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfRepositoryPathNotString()
    {
        new ResourceMapping(12345, 'resources');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfRepositoryPathEmpty()
    {
        new ResourceMapping('', 'resources');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfFilesystemPathsNotStringOrArray()
    {
        new ResourceMapping('/path', 12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfFilesystemPathsEmptyString()
    {
        new ResourceMapping('/path', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfFilesystemPathsNotStringArray()
    {
        new ResourceMapping('/path', array(12345));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfFilesystemPathsContainEmptyString()
    {
        new ResourceMapping('/path', array(''));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNoFilesystemPaths()
    {
        new ResourceMapping('/path', array());
    }

    public function testLoad()
    {
        $mapping = new ResourceMapping('/path', 'resources');

        $this->assertSame(array('resources'), $mapping->getPathReferences());
        $this->assertFalse($mapping->isLoaded());

        $mapping->load($this->package1, $this->packages);

        $this->assertSame(array('resources'), $mapping->getPathReferences());
        $this->assertSame(array($this->packageDir1.'/resources'), $mapping->getFilesystemPaths());
        $this->assertSame(array(), $mapping->getLoadErrors());
        $this->assertSame($this->package1, $mapping->getContainingPackage());
        $this->assertTrue($mapping->isLoaded());
    }

    public function testLoadMultipleFilesystemPaths()
    {
        $mapping = new ResourceMapping('/path', array('resources', 'assets'));

        $this->assertSame(array('resources', 'assets'), $mapping->getPathReferences());
        $this->assertFalse($mapping->isLoaded());

        $mapping->load($this->package1, $this->packages);

        $this->assertSame(array('resources', 'assets'), $mapping->getPathReferences());
        $this->assertSame(array(
            $this->packageDir1.'/resources',
            $this->packageDir1.'/assets',
            ), $mapping->getFilesystemPaths());
        $this->assertSame(array(), $mapping->getLoadErrors());
        $this->assertSame($this->package1, $mapping->getContainingPackage());
        $this->assertTrue($mapping->isLoaded());
    }

    public function testLoadReferencesToOtherPackage()
    {
        $mapping = new ResourceMapping('/path', '@vendor/package2:resources');

        $this->assertSame(array('@vendor/package2:resources'), $mapping->getPathReferences());
        $this->assertFalse($mapping->isLoaded());

        $mapping->load($this->package1, $this->packages);

        $this->assertSame(array('@vendor/package2:resources'), $mapping->getPathReferences());
        $this->assertSame(array($this->packageDir2.'/resources'), $mapping->getFilesystemPaths());
        $this->assertSame(array(), $mapping->getLoadErrors());
        $this->assertSame($this->package1, $mapping->getContainingPackage());
        $this->assertTrue($mapping->isLoaded());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Repository\MappingAlreadyLoadedException
     */
    public function testLoadFailsIfCalledTwice()
    {
        $mapping = new ResourceMapping('/path', 'resources');

        $mapping->load($this->package1, $this->packages);
        $mapping->load($this->package1, $this->packages);
    }

    public function testLoadStoresErrorIfPathNotFound()
    {
        $mapping = new ResourceMapping('/path', array('foo', 'assets'));

        $mapping->load($this->package1, $this->packages);

        $this->assertSame(array('foo', 'assets'), $mapping->getPathReferences());
        $this->assertSame(array($this->packageDir1.'/assets'), $mapping->getFilesystemPaths());

        // there's at least one found path, so the mapping is still enabled
        $this->assertTrue($mapping->isEnabled());

        $loadErrors = $mapping->getLoadErrors();
        $this->assertCount(1, $loadErrors);
        $this->assertInstanceOf('Puli\RepositoryManager\FileNotFoundException', $loadErrors[0]);
    }

    public function testLoadStoresErrorsIfNoPathFound()
    {
        $mapping = new ResourceMapping('/path', array('foo', 'bar'));

        $mapping->load($this->package1, $this->packages);

        $this->assertSame(array('foo', 'bar'), $mapping->getPathReferences());
        $this->assertSame(array(), $mapping->getFilesystemPaths());

        // no found path, not enabled
        $this->assertFalse($mapping->isEnabled());
        $this->assertTrue($mapping->isNotFound());

        $loadErrors = $mapping->getLoadErrors();
        $this->assertCount(2, $loadErrors);
        $this->assertInstanceOf('Puli\RepositoryManager\FileNotFoundException', $loadErrors[0]);
        $this->assertInstanceOf('Puli\RepositoryManager\FileNotFoundException', $loadErrors[1]);
    }

    public function testLoadStoresErrorIfPackageNotFound()
    {
        $mapping = new ResourceMapping('/path', array('@foo:resources', 'assets'));

        $mapping->load($this->package1, $this->packages);

        $this->assertSame(array('@foo:resources', 'assets'), $mapping->getPathReferences());
        $this->assertSame(array($this->packageDir1.'/assets'), $mapping->getFilesystemPaths());

        // there's at least one found path, so the mapping is still enabled
        $this->assertTrue($mapping->isEnabled());

        $loadErrors = $mapping->getLoadErrors();
        $this->assertCount(1, $loadErrors);
        $this->assertInstanceOf('Puli\RepositoryManager\Package\NoSuchPackageException', $loadErrors[0]);
    }

    public function testLoadFailsIfPathNotFoundAndFailOnError()
    {
        $mapping = new ResourceMapping('/path', array('assets', 'foo'));

        try {
            $mapping->load($this->package1, $this->packages, true);
            $this->fail('Expected a FileNotFoundException');
        } catch (FileNotFoundException $e) {
        }

        $this->assertSame(array('assets', 'foo'), $mapping->getPathReferences());
        $this->assertFalse($mapping->isLoaded());
    }

    public function testLoadFailsIfPackageNotFoundAndFailOnError()
    {
        $mapping = new ResourceMapping('/path', array('assets', '@foo:resources'));

        try {
            $mapping->load($this->package1, $this->packages, true);
            $this->fail('Expected a NoSuchPackageException');
        } catch (NoSuchPackageException $e) {
        }

        $this->assertSame(array('assets', '@foo:resources'), $mapping->getPathReferences());
        $this->assertFalse($mapping->isLoaded());
    }

    public function testUnload()
    {
        $mapping = new ResourceMapping('/path', 'resources');

        $mapping->load($this->package1, $this->packages);
        $mapping->unload();

        $this->assertSame(array('resources'), $mapping->getPathReferences());
        $this->assertFalse($mapping->isLoaded());
    }

    public function testUnloadReleasesConflict()
    {
        $mapping = new ResourceMapping('/path', 'resources');

        $mapping->load($this->package1, $this->packages);
        $mapping->addConflict($conflict = new RepositoryPathConflict('/path/conflict'));

        $this->assertCount(1, $mapping->getConflicts());

        $mapping->unload();

        $this->assertCount(0, $mapping->getConflicts());
        $this->assertCount(0, $conflict->getMappings());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Repository\MappingNotLoadedException
     */
    public function testUnloadFailsIfNotLoaded()
    {
        $mapping = new ResourceMapping('/path', 'resources');

        $mapping->unload();
    }

    public function testAddConflictWithAmePath()
    {
        $mapping = new ResourceMapping('/path', 'resources');
        $mapping->load($this->package1, $this->packages);
        $conflict = new RepositoryPathConflict('/path');

        $this->assertCount(0, $mapping->getConflicts());
        $this->assertCount(0, $conflict->getMappings());

        $mapping->addConflict($conflict);

        $this->assertCount(1, $mapping->getConflicts());
        $this->assertContains($conflict, $mapping->getConflicts());
        $this->assertCount(1, $conflict->getMappings());
        $this->assertContains($mapping, $conflict->getMappings());
        $this->assertTrue($mapping->isConflicting());
    }

    public function testAddConflictWithNestedPath()
    {
        $mapping = new ResourceMapping('/path', 'resources');
        $mapping->load($this->package1, $this->packages);
        $conflict = new RepositoryPathConflict('/path/conflict');

        $mapping->addConflict($conflict);

        $this->assertCount(1, $mapping->getConflicts());
        $this->assertContains($conflict, $mapping->getConflicts());
        $this->assertCount(1, $conflict->getMappings());
        $this->assertContains($mapping, $conflict->getMappings());
        $this->assertTrue($mapping->isConflicting());
    }

    public function testAddMultipleConflicts()
    {
        $mapping = new ResourceMapping('/path', 'resources');
        $mapping->load($this->package1, $this->packages);
        $conflict1 = new RepositoryPathConflict('/path/conflict1');
        $conflict2 = new RepositoryPathConflict('/path/conflict2');

        $this->assertCount(0, $mapping->getConflicts());
        $this->assertCount(0, $conflict1->getMappings());

        $mapping->addConflict($conflict1);
        $mapping->addConflict($conflict2);

        $this->assertCount(2, $mapping->getConflicts());
        $this->assertContains($conflict1, $mapping->getConflicts());
        $this->assertContains($conflict2, $mapping->getConflicts());
        $this->assertCount(1, $conflict1->getMappings());
        $this->assertContains($mapping, $conflict1->getMappings());
        $this->assertCount(1, $conflict2->getMappings());
        $this->assertContains($mapping, $conflict2->getMappings());
        $this->assertTrue($mapping->isConflicting());
    }

    public function testAddConflictIgnoresDuplicates()
    {
        $mapping = new ResourceMapping('/path', 'resources');
        $mapping->load($this->package1, $this->packages);
        $conflict = new RepositoryPathConflict('/path/conflict');

        $this->assertCount(0, $mapping->getConflicts());
        $this->assertCount(0, $conflict->getMappings());

        $mapping->addConflict($conflict);
        $mapping->addConflict($conflict);

        $this->assertCount(1, $mapping->getConflicts());
        $this->assertContains($conflict, $mapping->getConflicts());
        $this->assertCount(1, $conflict->getMappings());
        $this->assertContains($mapping, $conflict->getMappings());
        $this->assertTrue($mapping->isConflicting());
    }

    public function testAddConflictRemovesPreviousConflictWithSameRepositoryPath()
    {
        $mapping = new ResourceMapping('/path', 'resources');
        $mapping->load($this->package1, $this->packages);
        $previousConflict = new RepositoryPathConflict('/path/conflict');
        $newConflict = new RepositoryPathConflict('/path/conflict');

        $mapping->addConflict($previousConflict);
        $mapping->addConflict($newConflict);

        $this->assertCount(1, $mapping->getConflicts());
        $this->assertContains($newConflict, $mapping->getConflicts());
        $this->assertCount(0, $previousConflict->getMappings());
        $this->assertCount(1, $newConflict->getMappings());
        $this->assertContains($mapping, $newConflict->getMappings());
        $this->assertTrue($mapping->isConflicting());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Repository\MappingNotLoadedException
     */
    public function testAddConflictFailsIfNotLoaded()
    {
        $mapping = new ResourceMapping('/path', 'resources');
        $conflict = new RepositoryPathConflict('/path/conflict');

        $mapping->addConflict($conflict);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddConflictFailsIfConflictWithDifferentRepositoryBasePath()
    {
        $mapping = new ResourceMapping('/path', 'resources');
        $mapping->load($this->package1, $this->packages);
        $conflict = new RepositoryPathConflict('/other/path/conflict');

        $mapping->addConflict($conflict);
    }

    public function testRemoveConflict()
    {
        $mapping = new ResourceMapping('/path', 'resources');
        $mapping->load($this->package1, $this->packages);
        $conflict = new RepositoryPathConflict('/path/conflict');

        $mapping->addConflict($conflict);
        $mapping->removeConflict($conflict);

        $this->assertCount(0, $mapping->getConflicts());
        $this->assertCount(0, $conflict->getMappings());
        $this->assertFalse($mapping->isConflicting());
    }

    public function testRemoveConflictIgnoresUnknownConflicts()
    {
        $mapping = new ResourceMapping('/path', 'resources');
        $mapping->load($this->package1, $this->packages);
        $conflict = new RepositoryPathConflict('/path/conflict');

        $mapping->removeConflict($conflict);

        $this->assertCount(0, $mapping->getConflicts());
        $this->assertCount(0, $conflict->getMappings());
        $this->assertFalse($mapping->isConflicting());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Repository\MappingNotLoadedException
     */
    public function testRemoveConflictFailsIfNotLoaded()
    {
        $mapping = new ResourceMapping('/path', 'resources');
        $conflict = new RepositoryPathConflict('/path/conflict');

        $mapping->removeConflict($conflict);
    }

    public function testGetConflictingPackages()
    {
        $mapping1 = new ResourceMapping('/path', 'resources');
        $mapping1->load($this->package1, $this->packages);

        $this->assertInstanceOf('Puli\RepositoryManager\Package\PackageCollection', $mapping1->getConflictingPackages());
        $this->assertCount(0, $mapping1->getConflictingPackages());

        $mapping2 = new ResourceMapping('/path', 'resources');
        $mapping2->load($this->package2, $this->packages);

        $conflict = new RepositoryPathConflict('/path/conflict');

        $mapping1->addConflict($conflict);
        $mapping2->addConflict($conflict);

        $this->assertCount(1, $mapping1->getConflictingPackages());
        $this->assertTrue($mapping1->getConflictingPackages()->contains('vendor/package2'));
        $this->assertCount(1, $mapping2->getConflictingPackages());
        $this->assertTrue($mapping2->getConflictingPackages()->contains('vendor/package1'));

        $mapping3 = new ResourceMapping('/path', 'resources');
        $mapping3->load($this->package3, $this->packages);
        $mapping3->addConflict($conflict);

        $this->assertCount(2, $mapping1->getConflictingPackages());
        $this->assertTrue($mapping1->getConflictingPackages()->contains('vendor/package2'));
        $this->assertTrue($mapping1->getConflictingPackages()->contains('vendor/package3'));
        $this->assertCount(2, $mapping2->getConflictingPackages());
        $this->assertTrue($mapping2->getConflictingPackages()->contains('vendor/package1'));
        $this->assertTrue($mapping2->getConflictingPackages()->contains('vendor/package3'));
        $this->assertCount(2, $mapping3->getConflictingPackages());
        $this->assertTrue($mapping3->getConflictingPackages()->contains('vendor/package1'));
        $this->assertTrue($mapping3->getConflictingPackages()->contains('vendor/package2'));
    }

    public function testGetConflictingMappings()
    {
        $mapping1 = new ResourceMapping('/path', 'resources');
        $mapping1->load($this->package1, $this->packages);

        $this->assertCount(0, $mapping1->getConflictingMappings());

        $mapping2 = new ResourceMapping('/path', 'resources');
        $mapping2->load($this->package2, $this->packages);

        $conflict = new RepositoryPathConflict('/path/conflict');

        $mapping1->addConflict($conflict);
        $mapping2->addConflict($conflict);

        $this->assertCount(1, $mapping1->getConflictingMappings());
        $this->assertContains($mapping2, $mapping1->getConflictingMappings());
        $this->assertCount(1, $mapping2->getConflictingMappings());
        $this->assertContains($mapping1, $mapping2->getConflictingMappings());

        $mapping3 = new ResourceMapping('/path', 'resources');
        $mapping3->load($this->package3, $this->packages);
        $mapping3->addConflict($conflict);

        $this->assertCount(2, $mapping1->getConflictingMappings());
        $this->assertContains($mapping2, $mapping1->getConflictingMappings());
        $this->assertContains($mapping3, $mapping1->getConflictingMappings());
        $this->assertCount(2, $mapping2->getConflictingMappings());
        $this->assertContains($mapping1, $mapping2->getConflictingMappings());
        $this->assertContains($mapping3, $mapping2->getConflictingMappings());
        $this->assertCount(2, $mapping3->getConflictingMappings());
        $this->assertContains($mapping1, $mapping3->getConflictingMappings());
        $this->assertContains($mapping2, $mapping3->getConflictingMappings());
    }
}
