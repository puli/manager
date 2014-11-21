<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Package\Config;

use Puli\PackageManager\Package\Config\PackageConfig;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageConfigTest extends \PHPUnit_Framework_TestCase
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
        $config = new PackageConfig(null, $path);

        $this->assertSame($path, $config->getPath());
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
        new PackageConfig(null, $invalidPath);
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
        $config = new PackageConfig($name);

        $this->assertSame($name, $config->getPackageName());
    }

    /**
     * @dataProvider provideValidPackageNames
     */
    public function testGetPackageNameSetter($name)
    {
        $config = new PackageConfig();
        $config->setPackageName($name);

        $this->assertSame($name, $config->getPackageName());
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
        new PackageConfig($invalidName);
    }

    /**
     * @dataProvider provideInvalidPaths
     * @expectedException \InvalidArgumentException
     */
    public function testPackageNameMustBeValidSetter($invalidName)
    {
        $config = new PackageConfig();
        $config->setPackageName($invalidName);
    }
}
