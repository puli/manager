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
use Puli\RepositoryManager\Package\InstallFile\PackageMetadata;

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

    public function testAddPackageMetadata()
    {
        $metadata1 = new PackageMetadata('/path/to/package1');
        $metadata2 = new PackageMetadata('/path/to/package2');

        $installFile = new InstallFile();
        $installFile->addPackageMetadata($metadata1);
        $installFile->addPackageMetadata($metadata2);

        $this->assertSame(array($metadata1, $metadata2), $installFile->listPackageMetadata());
        $this->assertSame($metadata1, $installFile->getPackageMetadata('/path/to/package1'));
        $this->assertSame($metadata2, $installFile->getPackageMetadata('/path/to/package2'));
    }

    public function testClearPackageMetadata()
    {
        $installFile = new InstallFile();
        $installFile->addPackageMetadata(new PackageMetadata('/path/to/package1'));
        $installFile->clearPackageMetadata();

        $this->assertSame(array(), $installFile->listPackageMetadata());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Package\NoSuchPackageException
     * @expectedExceptionMessage /foo/bar
     */
    public function testGetPackageMetadataFailsIfNotFound()
    {
        $installFile = new InstallFile();
        $installFile->getPackageMetadata('/foo/bar');
    }

    public function testRemovePackageMetadata()
    {
        $metadata1 = new PackageMetadata('/path/to/package1');
        $metadata2 = new PackageMetadata('/path/to/package2');

        $installFile = new InstallFile();
        $installFile->addPackageMetadata($metadata1);
        $installFile->addPackageMetadata($metadata2);

        $installFile->removePackageMetadata('/path/to/package1');

        $this->assertSame(array($metadata2), $installFile->listPackageMetadata());
    }

    public function testRemoveIgnoresUnknownPaths()
    {
        $installFile = new InstallFile();
        $installFile->removePackageMetadata('/foo/bar');
    }

    public function testHasPackageMetadata()
    {
        $installFile = new InstallFile();

        $this->assertFalse($installFile->hasPackageMetadata('/path/to/package'));

        $installFile->addPackageMetadata(new PackageMetadata('/path/to/package'));

        $this->assertTrue($installFile->hasPackageMetadata('/path/to/package'));
    }
}
