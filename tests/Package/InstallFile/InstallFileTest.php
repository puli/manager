<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\InstallFile;

use Puli\RepositoryManager\Package\InstallFile\InstallFile;
use Puli\RepositoryManager\Package\InstallFile\PackageDescriptor;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallFileTest extends \PHPUnit_Framework_TestCase
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
        $installFile = new InstallFile($path);

        $this->assertSame($path, $installFile->getPath());
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
        new InstallFile($invalidPath);
    }

    public function testAddPackageDescriptor()
    {
        $descriptor1 = new PackageDescriptor('/path/to/package1');
        $descriptor2 = new PackageDescriptor('/path/to/package2');

        $installFile = new InstallFile();
        $installFile->addPackageDescriptor($descriptor1);
        $installFile->addPackageDescriptor($descriptor2);

        $this->assertSame(array($descriptor1, $descriptor2), $installFile->getPackageDescriptors());
        $this->assertSame($descriptor1, $installFile->getPackageDescriptor('/path/to/package1'));
        $this->assertSame($descriptor2, $installFile->getPackageDescriptor('/path/to/package2'));
    }

    public function testSetPackageDescriptors()
    {
        $descriptor1 = new PackageDescriptor('/path/to/package1');
        $descriptor2 = new PackageDescriptor('/path/to/package2');

        $installFile = new InstallFile();
        $installFile->setPackageDescriptors(array(
            $descriptor1,
            $descriptor2,
        ));

        $this->assertSame(array($descriptor1, $descriptor2), $installFile->getPackageDescriptors());
        $this->assertSame($descriptor1, $installFile->getPackageDescriptor('/path/to/package1'));
        $this->assertSame($descriptor2, $installFile->getPackageDescriptor('/path/to/package2'));
    }

    public function testSetPackageDescriptorsRemovesPreviousDescriptors()
    {
        $descriptor1 = new PackageDescriptor('/path/to/package1');
        $descriptor2 = new PackageDescriptor('/path/to/package2');

        $installFile = new InstallFile();
        $installFile->addPackageDescriptor($descriptor1);
        $installFile->setPackageDescriptors(array($descriptor2));

        $this->assertSame(array($descriptor2), $installFile->getPackageDescriptors());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Package\NoSuchPackageException
     * @expectedExceptionMessage /foo/bar
     */
    public function testGetPackageDescriptorFailsIfNotFound()
    {
        $installFile = new InstallFile();
        $installFile->getPackageDescriptor('/foo/bar');
    }

    public function testRemovePackageDescriptor()
    {
        $descriptor1 = new PackageDescriptor('/path/to/package1');
        $descriptor2 = new PackageDescriptor('/path/to/package2');

        $installFile = new InstallFile();
        $installFile->setPackageDescriptors(array(
            $descriptor1,
            $descriptor2,
        ));

        $installFile->removePackageDescriptor('/path/to/package1');

        $this->assertSame(array($descriptor2), $installFile->getPackageDescriptors());
    }

    public function testRemoveIgnoresUnknownPaths()
    {
        $installFile = new InstallFile();
        $installFile->removePackageDescriptor('/foo/bar');
    }

    public function testHasPackageDescriptor()
    {
        $installFile = new InstallFile();

        $this->assertFalse($installFile->hasPackageDescriptor('/path/to/package'));

        $installFile->addPackageDescriptor(new PackageDescriptor('/path/to/package'));

        $this->assertTrue($installFile->hasPackageDescriptor('/path/to/package'));
    }
}
