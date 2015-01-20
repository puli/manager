<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Api\Package;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Api\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Api\Package\PackageFile;
use Puli\RepositoryManager\Api\Repository\ResourceMapping;
use Rhumsaa\Uuid\Uuid;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileTest extends PHPUnit_Framework_TestCase
{
    public function provideValidPaths()
    {
        return array(
            array(null),
            array('/foo'),
        );
    }

    /**
     * @dataProvider provideValidPaths
     */
    public function testGetPath($path)
    {
        $packageFile = new PackageFile(null, $path);

        $this->assertSame($path, $packageFile->getPath());
    }

    public function provideInvalidPaths()
    {
        return array(
            array(12345),
            array(''),
        );
    }

    /**
     * @dataProvider provideInvalidPaths
     * @expectedException \InvalidArgumentException
     */
    public function testPathMustBeValid($invalidPath)
    {
        new PackageFile(null, $invalidPath);
    }

    public function provideValidPackageNames()
    {
        return array(
            array(null),
            array('/foo'),
        );
    }

    /**
     * @dataProvider provideValidPackageNames
     */
    public function testGetPackageName($name)
    {
        $packageFile = new PackageFile($name);

        $this->assertSame($name, $packageFile->getPackageName());
    }

    /**
     * @dataProvider provideValidPackageNames
     */
    public function testGetPackageNameSetter($name)
    {
        $packageFile = new PackageFile();
        $packageFile->setPackageName($name);

        $this->assertSame($name, $packageFile->getPackageName());
    }

    public function provideInvalidPackageNames()
    {
        return array(
            array(12345),
            array(''),
        );
    }

    /**
     * @dataProvider provideInvalidPaths
     * @expectedException \InvalidArgumentException
     */
    public function testPackageNameMustBeValid($invalidName)
    {
        new PackageFile($invalidName);
    }

    /**
     * @dataProvider provideInvalidPaths
     * @expectedException \InvalidArgumentException
     */
    public function testPackageNameMustBeValidSetter($invalidName)
    {
        $packageFile = new PackageFile();
        $packageFile->setPackageName($invalidName);
    }

    public function testAddResourceMapping()
    {
        $mapping1 = new ResourceMapping('/path1', 'res1');
        $mapping2 = new ResourceMapping('/path2', array('res2', 'res3'));

        $packageFile = new PackageFile();
        $packageFile->addResourceMapping($mapping1);
        $packageFile->addResourceMapping($mapping2);

        $this->assertSame(array(
            '/path1' => $mapping1,
            '/path2' => $mapping2,
        ), $packageFile->getResourceMappings());
    }

    public function testGetResourceMappingsReturnsSortedResult()
    {
        $mapping1 = new ResourceMapping('/path1', 'res1');
        $mapping2 = new ResourceMapping('/path2', 'res2');
        $mapping3 = new ResourceMapping('/path3', 'res3');

        $packageFile = new PackageFile();
        $packageFile->addResourceMapping($mapping3);
        $packageFile->addResourceMapping($mapping1);
        $packageFile->addResourceMapping($mapping2);

        $this->assertSame(array(
            '/path1' => $mapping1,
            '/path2' => $mapping2,
            '/path3' => $mapping3,
        ), $packageFile->getResourceMappings());
    }

    public function testGetResourceMapping()
    {
        $mapping1 = new ResourceMapping('/path1', 'res1');
        $mapping2 = new ResourceMapping('/path2', array('res2', 'res3'));

        $packageFile = new PackageFile();
        $packageFile->addResourceMapping($mapping1);
        $packageFile->addResourceMapping($mapping2);

        $this->assertSame($mapping1, $packageFile->getResourceMapping('/path1'));
        $this->assertSame($mapping2, $packageFile->getResourceMapping('/path2'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\Repository\NoSuchMappingException
     * @expectedExceptionMessage foobar
     */
    public function testGetResourceMappingFailsIfPathNotFound()
    {
        $packageFile = new PackageFile();

        $packageFile->getResourceMapping('/foobar');
    }

    public function testHasResourceMapping()
    {
        $packageFile = new PackageFile();

        $this->assertFalse($packageFile->hasResourceMapping('/path'));

        $packageFile->addResourceMapping(new ResourceMapping('/path', 'res'));

        $this->assertTrue($packageFile->hasResourceMapping('/path'));
    }

    public function testRemoveResourceMapping()
    {
        $packageFile = new PackageFile();

        $packageFile->addResourceMapping(new ResourceMapping('/path', 'res'));
        $packageFile->removeResourceMapping('/path');

        $this->assertFalse($packageFile->hasResourceMapping('/path'));
    }

    public function testRemoveResourceMappingIgnoresUnknownPaths()
    {
        $packageFile = new PackageFile();
        $packageFile->removeResourceMapping('/foobar');

        $this->assertFalse($packageFile->hasResourceMapping('/foobar'));
    }

    public function testSetOverriddenPackages()
    {
        $packageFile = new PackageFile();
        $packageFile->setOverriddenPackages(array('package1', 'package2'));

        $this->assertSame(array('package1', 'package2'), $packageFile->getOverriddenPackages());
    }

    public function testAddOverriddenPackage()
    {
        $packageFile = new PackageFile();
        $packageFile->setOverriddenPackages(array('package1'));
        $packageFile->addOverriddenPackage('package2');

        $this->assertSame(array('package1', 'package2'), $packageFile->getOverriddenPackages());
    }

    public function testAddOverriddenPackageIgnoresDuplicates()
    {
        $packageFile = new PackageFile();
        $packageFile->setOverriddenPackages(array('package1'));
        $packageFile->addOverriddenPackage('package1');

        $this->assertSame(array('package1'), $packageFile->getOverriddenPackages());
    }

    public function testAddBindingDescriptor()
    {
        $packageFile = new PackageFile();
        $packageFile->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));

        $this->assertSame($binding, $packageFile->getBindingDescriptor($binding->getUuid()));
        $this->assertSame(array($binding), $packageFile->getBindingDescriptors());
    }

    public function testRemoveBindingDescriptor()
    {
        $packageFile = new PackageFile();
        $packageFile->addBindingDescriptor($binding1 = BindingDescriptor::create('/path1', 'my/type'));
        $packageFile->addBindingDescriptor($binding2 = BindingDescriptor::create('/path2', 'my/type'));
        $packageFile->removeBindingDescriptor($binding1->getUuid());

        $this->assertSame(array($binding2), $packageFile->getBindingDescriptors());
    }

    public function testHasBindingDescriptor()
    {
        $packageFile = new PackageFile();
        $binding = BindingDescriptor::create('/path', 'my/type');

        $this->assertFalse($packageFile->hasBindingDescriptor($binding->getUuid()));
        $packageFile->addBindingDescriptor($binding);
        $this->assertTrue($packageFile->hasBindingDescriptor($binding->getUuid()));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\Discovery\NoSuchBindingException
     * @expectedExceptionMessage 8546da2c-dfec-48be-8cd3-93798c41b72f
     */
    public function testGetBindingDescriptorFailsIfUnknownUuid()
    {
        $packageFile = new PackageFile();
        $packageFile->getBindingDescriptor(Uuid::fromString('8546da2c-dfec-48be-8cd3-93798c41b72f'));
    }
}
