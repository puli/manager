<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\PackageFile;

use Puli\RepositoryManager\Package\PackageFile\PackageFile;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileTest extends \PHPUnit_Framework_TestCase
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
}
