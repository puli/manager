<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package;

use Puli\RepositoryManager\Config\GlobalConfig;
use Puli\RepositoryManager\Package\Config\RootPackageConfig;
use Puli\RepositoryManager\Package\RootPackage;

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
