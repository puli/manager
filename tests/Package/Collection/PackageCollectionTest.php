<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Package\Collection;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\Package;
use Puli\PackageManager\Package\Collection\PackageCollection;
use Puli\PackageManager\Package\RootPackage;

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
        $config = new PackageConfig('package');
        $package = new Package($config, '/path');

        $this->collection->add($package);

        $this->assertSame($package, $this->collection->get('package'));
    }

    /**
     * @expectedException \Puli\PackageManager\Package\Collection\NoSuchPackageException
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
        $config1 = new PackageConfig('package1');
        $package1 = new Package($config1, '/path1');

        $config2 = new PackageConfig('package2');
        $package2 = new Package($config2, '/path2');

        $globalConfig = new GlobalConfig();
        $rootConfig = new RootPackageConfig($globalConfig, 'root');
        $rootPackage = new RootPackage($rootConfig, '/path3');

        $this->collection->add($package1);
        $this->collection->add($rootPackage);
        $this->collection->add($package2);

        $this->assertSame($rootPackage, $this->collection->getRootPackage());
    }

    public function testRemove()
    {
        $config1 = new PackageConfig('package1');
        $package1 = new Package($config1, '/path1');

        $config2 = new PackageConfig('package2');
        $package2 = new Package($config2, '/path2');

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
        $config1 = new PackageConfig('package1');
        $package1 = new Package($config1, '/path1');

        $config2 = new PackageConfig('package2');
        $package2 = new Package($config2, '/path2');

        $globalConfig = new GlobalConfig();
        $rootConfig = new RootPackageConfig($globalConfig, 'root');
        $rootPackage = new RootPackage($rootConfig, '/path3');

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
        $config1 = new PackageConfig('package1');
        $package1 = new Package($config1, '/path1');

        $config2 = new PackageConfig('package2');
        $package2 = new Package($config2, '/path2');

        $globalConfig = new GlobalConfig();
        $rootConfig = new RootPackageConfig($globalConfig, 'root');
        $rootPackage = new RootPackage($rootConfig, '/path3');

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
        $config1 = new PackageConfig('package1');
        $package1 = new Package($config1, '/path1');

        $config2 = new PackageConfig('package2');
        $package2 = new Package($config2, '/path2');

        $globalConfig = new GlobalConfig();
        $rootConfig = new RootPackageConfig($globalConfig, 'root');
        $rootPackage = new RootPackage($rootConfig, '/path3');

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
        $config1 = new PackageConfig('package1');
        $package1 = new Package($config1, '/path1');

        $config2 = new PackageConfig('package2');
        $package2 = new Package($config2, '/path2');

        $globalConfig = new GlobalConfig();
        $rootConfig = new RootPackageConfig($globalConfig, 'root');
        $rootPackage = new RootPackage($rootConfig, '/path3');

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
