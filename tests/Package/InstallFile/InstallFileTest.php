<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\InstallFile;

use Puli\RepositoryManager\Package\InstallFile\InstallFile;
use Puli\RepositoryManager\Package\InstallInfo;

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

    public function testAddInstallInfo()
    {
        $installInfo1 = new InstallInfo('/path/to/package1');
        $installInfo2 = new InstallInfo('/path/to/package2');

        $installFile = new InstallFile();
        $installFile->addInstallInfo($installInfo1);
        $installFile->addInstallInfo($installInfo2);

        $this->assertSame(array($installInfo1, $installInfo2), $installFile->getInstallInfos());
        $this->assertSame($installInfo1, $installFile->getInstallInfo('/path/to/package1'));
        $this->assertSame($installInfo2, $installFile->getInstallInfo('/path/to/package2'));
    }

    public function testSetInstallInfos()
    {
        $installInfo1 = new InstallInfo('/path/to/package1');
        $installInfo2 = new InstallInfo('/path/to/package2');

        $installFile = new InstallFile();
        $installFile->setInstallInfos(array($installInfo1, $installInfo2));

        $this->assertSame(array($installInfo1, $installInfo2), $installFile->getInstallInfos());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Package\NoSuchPackageException
     * @expectedExceptionMessage /foo/bar
     */
    public function testGetInstallInfosFailsIfNotFound()
    {
        $installFile = new InstallFile();
        $installFile->getInstallInfo('/foo/bar');
    }

    public function testRemoveInstallInfo()
    {
        $installInfo1 = new InstallInfo('/path/to/package1');
        $installInfo2 = new InstallInfo('/path/to/package2');

        $installFile = new InstallFile();
        $installFile->addInstallInfo($installInfo1);
        $installFile->addInstallInfo($installInfo2);

        $installFile->removeInstallInfo('/path/to/package1');

        $this->assertSame(array($installInfo2), $installFile->getInstallInfos());
    }

    public function testRemoveInstallInfoIgnoresUnknownPaths()
    {
        $installFile = new InstallFile();
        $installFile->removeInstallInfo('/foo/bar');
    }

    public function testHasInstallInfo()
    {
        $installFile = new InstallFile();

        $this->assertFalse($installFile->hasInstallInfo('/path/to/package'));

        $installFile->addInstallInfo(new InstallInfo('/path/to/package'));

        $this->assertTrue($installFile->hasInstallInfo('/path/to/package'));
    }
}
