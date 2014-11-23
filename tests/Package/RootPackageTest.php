<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Package;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\RootPackage;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageTest extends \PHPUnit_Framework_TestCase
{
    public function testPackageName()
    {
        $globalConfig = new GlobalConfig();
        $config = new RootPackageConfig($globalConfig, 'name');
        $package = new RootPackage($config, '/path');

        $this->assertSame('name', $package->getName());
    }

    public function testPackageNameSetToDefaultIfEmpty()
    {
        $globalConfig = new GlobalConfig();
        $config = new RootPackageConfig($globalConfig);
        $package = new RootPackage($config, '/path');

        $this->assertSame('__root__', $package->getName());
    }
}
