<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\Collection;

use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\RootPackage;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageCollectionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PackageCollection
     */
    private $collection;

    protected function setUp()
    {
        $this->collection = new PackageCollection();
    }

    public function testGetPackage()
    {
        $packageFile = new PackageFile('package');
        $package = new Package($packageFile, '/path');

        $this->collection->add($package);

        $this->assertSame($package, $this->collection->get('package'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Package\Collection\NoSuchPackageException
     */
    public function testGetPackageFailsIfNotFound()
    {
        $this->collection->get('package');
    }

    public function testGetRootPackageReturnsNull()
    {
        $this->assertNull($this->collection->getRootPackage());
    }

    public function testGetRootPackageReturnsAddedRootPackage()
    {
        $packageFile1 = new PackageFile('package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('package2');
        $package2 = new Package($packageFile2, '/path2');

        $rootPackageFile = new RootPackageFile('root');
        $rootPackage = new RootPackage($rootPackageFile, '/path3');

        $this->collection->add($package1);
        $this->collection->add($rootPackage);
        $this->collection->add($package2);

        $this->assertSame($rootPackage, $this->collection->getRootPackage());
    }

    public function testRemove()
    {
        $packageFile1 = new PackageFile('package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('package2');
        $package2 = new Package($packageFile2, '/path2');

        $this->collection->add($package1);
        $this->collection->add($package2);

        $this->collection->remove('package1');

        $this->assertFalse($this->collection->contains('package1'));
        $this->assertTrue($this->collection->contains('package2'));
    }

    public function testRemoveUnknown()
    {
        $this->collection->remove('foo');

        $this->assertFalse($this->collection->contains('foo'));
    }

    public function testRemoveRoot()
    {
        $packageFile1 = new PackageFile('package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('package2');
        $package2 = new Package($packageFile2, '/path2');

        $rootPackageFile = new RootPackageFile('root');
        $rootPackage = new RootPackage($rootPackageFile, '/path3');

        $this->collection->add($package1);
        $this->collection->add($rootPackage);
        $this->collection->add($package2);

        $this->collection->remove('root');

        $this->assertFalse($this->collection->contains('root'));
        $this->assertTrue($this->collection->contains('package1'));
        $this->assertTrue($this->collection->contains('package2'));

        $this->assertNull($this->collection->getRootPackage());
    }

    public function testIterate()
    {
        $packageFile1 = new PackageFile('package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('package2');
        $package2 = new Package($packageFile2, '/path2');

        $rootPackageFile = new RootPackageFile('root');
        $rootPackage = new RootPackage($rootPackageFile, '/path3');

        $this->collection->add($package1);
        $this->collection->add($rootPackage);
        $this->collection->add($package2);

        $this->assertSame(array(
            'package1' => $package1,
            'root' => $rootPackage,
            'package2' => $package2,
        ), iterator_to_array($this->collection));
    }

    public function testToArray()
    {
        $packageFile1 = new PackageFile('package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('package2');
        $package2 = new Package($packageFile2, '/path2');

        $rootPackageFile = new RootPackageFile('root');
        $rootPackage = new RootPackage($rootPackageFile, '/path3');

        $this->collection->add($package1);
        $this->collection->add($rootPackage);
        $this->collection->add($package2);

        $this->assertSame(array(
            'package1' => $package1,
            'root' => $rootPackage,
            'package2' => $package2,
        ), $this->collection->toArray());
    }

    public function testArrayAccess()
    {
        $packageFile1 = new PackageFile('package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('package2');
        $package2 = new Package($packageFile2, '/path2');

        $rootPackageFile = new RootPackageFile('root');
        $rootPackage = new RootPackage($rootPackageFile, '/path3');

        $this->assertFalse(isset($this->collection['package1']));
        $this->assertFalse(isset($this->collection['package2']));
        $this->assertFalse(isset($this->collection['root']));

        $this->collection[] = $package1;
        $this->collection[] = $package2;
        $this->collection[] = $rootPackage;

        $this->assertTrue(isset($this->collection['package1']));
        $this->assertTrue(isset($this->collection['package2']));
        $this->assertTrue(isset($this->collection['root']));

        $this->assertSame($rootPackage, $this->collection['root']);
        $this->assertSame($rootPackage, $this->collection->getRootPackage());
        $this->assertSame($package1, $this->collection['package1']);
        $this->assertSame($package2, $this->collection['package2']);

        unset($this->collection['package1']);

        $this->assertFalse(isset($this->collection['package1']));
        $this->assertTrue(isset($this->collection['package2']));
        $this->assertTrue(isset($this->collection['root']));
    }
}
