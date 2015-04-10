<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Package;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageCollectionTest extends PHPUnit_Framework_TestCase
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
        $packageFile = new PackageFile('vendor/package');
        $package = new Package($packageFile, '/path');

        $this->collection->add($package);

        $this->assertSame($package, $this->collection->get('vendor/package'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Package\NoSuchPackageException
     */
    public function testGetPackageFailsIfNotFound()
    {
        $this->collection->get('vendor/package');
    }

    public function testGetRootPackageReturnsNull()
    {
        $this->assertNull($this->collection->getRootPackage());
    }

    public function testGetRootPackageReturnsAddedRootPackage()
    {
        $packageFile1 = new PackageFile('vendor/package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('vendor/package2');
        $package2 = new Package($packageFile2, '/path2');

        $rootPackageFile = new RootPackageFile('vendor/root');
        $rootPackage = new RootPackage($rootPackageFile, '/path3');

        $this->collection->add($package1);
        $this->collection->add($rootPackage);
        $this->collection->add($package2);

        $this->assertSame($rootPackage, $this->collection->getRootPackage());
    }

    public function testGetRootPackageName()
    {
        $rootPackageFile = new RootPackageFile('vendor/root');
        $rootPackage = new RootPackage($rootPackageFile, '/path');

        $this->collection->add($rootPackage);

        $this->assertSame('vendor/root', $this->collection->getRootPackageName());
    }

    public function testGetRootPackageNameReturnsNullIfNoRootPackage()
    {
        $packageFile = new PackageFile('vendor/package');
        $package = new Package($packageFile, '/path');

        $this->collection->add($package);

        $this->assertNull($this->collection->getRootPackageName());
    }

    public function testGetInstalledPackages()
    {
        $packageFile1 = new PackageFile('vendor/package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('vendor/package2');
        $package2 = new Package($packageFile2, '/path2');

        $this->collection->add($package1);
        $this->collection->add($package2);

        $this->assertSame(array(
            'vendor/package1' => $package1,
            'vendor/package2' => $package2
        ), $this->collection->getInstalledPackages());
    }

    public function testGetInstalledPackagesDoesNotIncludeRoot()
    {
        $packageFile1 = new PackageFile('vendor/package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('vendor/package2');
        $package2 = new Package($packageFile2, '/path2');

        $rootPackageFile = new RootPackageFile('vendor/root');
        $rootPackage = new RootPackage($rootPackageFile, '/path3');

        $this->collection->add($package1);
        $this->collection->add($rootPackage);
        $this->collection->add($package2);

        $this->assertSame(array(
            'vendor/package1' => $package1,
            'vendor/package2' => $package2
        ), $this->collection->getInstalledPackages());
    }

    public function testGetInstalledPackageNames()
    {
        $packageFile1 = new PackageFile('vendor/package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('vendor/package2');
        $package2 = new Package($packageFile2, '/path2');

        $this->collection->add($package1);
        $this->collection->add($package2);

        $this->assertSame(array('vendor/package1', 'vendor/package2'), $this->collection->getInstalledPackageNames());
    }

    public function testMerge()
    {
        $packageFile1 = new PackageFile('vendor/package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('vendor/package2');
        $package2 = new Package($packageFile2, '/path2');

        $packageFile3 = new PackageFile('vendor/package3');
        $package3 = new Package($packageFile3, '/path3');

        $this->collection->add($package1);
        $this->collection->merge(array($package2, $package3));

        $this->assertSame(array(
            'vendor/package1' => $package1,
            'vendor/package2' => $package2,
            'vendor/package3' => $package3,
        ), $this->collection->toArray());
    }

    public function testReplace()
    {
        $packageFile1 = new PackageFile('vendor/package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('vendor/package2');
        $package2 = new Package($packageFile2, '/path2');

        $packageFile3 = new PackageFile('vendor/package3');
        $package3 = new Package($packageFile3, '/path3');

        $this->collection->add($package1);
        $this->collection->replace(array($package2, $package3));

        $this->assertSame(array(
            'vendor/package2' => $package2,
            'vendor/package3' => $package3,
        ), $this->collection->toArray());
    }

    public function testRemove()
    {
        $packageFile1 = new PackageFile('vendor/package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('vendor/package2');
        $package2 = new Package($packageFile2, '/path2');

        $this->collection->add($package1);
        $this->collection->add($package2);

        $this->collection->remove('vendor/package1');

        $this->assertFalse($this->collection->contains('vendor/package1'));
        $this->assertTrue($this->collection->contains('vendor/package2'));
    }

    public function testRemoveUnknown()
    {
        $this->collection->remove('foo');

        $this->assertFalse($this->collection->contains('foo'));
    }

    public function testRemoveRoot()
    {
        $packageFile1 = new PackageFile('vendor/package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('vendor/package2');
        $package2 = new Package($packageFile2, '/path2');

        $rootPackageFile = new RootPackageFile('vendor/root');
        $rootPackage = new RootPackage($rootPackageFile, '/path3');

        $this->collection->add($package1);
        $this->collection->add($rootPackage);
        $this->collection->add($package2);

        $this->collection->remove('vendor/root');

        $this->assertFalse($this->collection->contains('vendor/root'));
        $this->assertTrue($this->collection->contains('vendor/package1'));
        $this->assertTrue($this->collection->contains('vendor/package2'));

        $this->assertNull($this->collection->getRootPackage());
    }

    public function testClear()
    {
        $packageFile1 = new PackageFile('vendor/package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('vendor/package2');
        $package2 = new Package($packageFile2, '/path2');

        $rootPackageFile = new RootPackageFile('vendor/root');
        $rootPackage = new RootPackage($rootPackageFile, '/path3');

        $this->collection->add($package1);
        $this->collection->add($rootPackage);
        $this->collection->add($package2);

        $this->collection->clear();

        $this->assertFalse($this->collection->contains('vendor/root'));
        $this->assertFalse($this->collection->contains('vendor/package1'));
        $this->assertFalse($this->collection->contains('vendor/package2'));
        $this->assertTrue($this->collection->isEmpty());
    }

    public function testIterate()
    {
        $packageFile1 = new PackageFile('vendor/package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('vendor/package2');
        $package2 = new Package($packageFile2, '/path2');

        $rootPackageFile = new RootPackageFile('vendor/root');
        $rootPackage = new RootPackage($rootPackageFile, '/path3');

        $this->collection->add($package1);
        $this->collection->add($rootPackage);
        $this->collection->add($package2);

        $this->assertSame(array(
            'vendor/package1' => $package1,
            'vendor/root' => $rootPackage,
            'vendor/package2' => $package2,
        ), iterator_to_array($this->collection));
    }

    public function testToArray()
    {
        $packageFile1 = new PackageFile('vendor/package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('vendor/package2');
        $package2 = new Package($packageFile2, '/path2');

        $rootPackageFile = new RootPackageFile('vendor/root');
        $rootPackage = new RootPackage($rootPackageFile, '/path3');

        $this->collection->add($package1);
        $this->collection->add($rootPackage);
        $this->collection->add($package2);

        $this->assertSame(array(
            'vendor/package1' => $package1,
            'vendor/root' => $rootPackage,
            'vendor/package2' => $package2,
        ), $this->collection->toArray());
    }

    public function testArrayAccess()
    {
        $packageFile1 = new PackageFile('vendor/package1');
        $package1 = new Package($packageFile1, '/path1');

        $packageFile2 = new PackageFile('vendor/package2');
        $package2 = new Package($packageFile2, '/path2');

        $rootPackageFile = new RootPackageFile('vendor/root');
        $rootPackage = new RootPackage($rootPackageFile, '/path3');

        $this->assertFalse(isset($this->collection['vendor/package1']));
        $this->assertFalse(isset($this->collection['vendor/package2']));
        $this->assertFalse(isset($this->collection['vendor/root']));

        $this->collection[] = $package1;
        $this->collection[] = $package2;
        $this->collection[] = $rootPackage;

        $this->assertTrue(isset($this->collection['vendor/package1']));
        $this->assertTrue(isset($this->collection['vendor/package2']));
        $this->assertTrue(isset($this->collection['vendor/root']));

        $this->assertSame($rootPackage, $this->collection['vendor/root']);
        $this->assertSame($rootPackage, $this->collection->getRootPackage());
        $this->assertSame($package1, $this->collection['vendor/package1']);
        $this->assertSame($package2, $this->collection['vendor/package2']);

        unset($this->collection['vendor/package1']);

        $this->assertFalse(isset($this->collection['vendor/package1']));
        $this->assertTrue(isset($this->collection['vendor/package2']));
        $this->assertTrue(isset($this->collection['vendor/root']));
    }
}
