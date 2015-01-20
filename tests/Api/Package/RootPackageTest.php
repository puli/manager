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
use Puli\RepositoryManager\Api\Package\RootPackage;
use Puli\RepositoryManager\Api\Package\RootPackageFile;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageTest extends PHPUnit_Framework_TestCase
{
    public function testPackageName()
    {
        $packageFile = new RootPackageFile('vendor/name');
        $package = new RootPackage($packageFile, '/path');

        $this->assertSame('vendor/name', $package->getName());
    }

    public function testPackageNameSetToDefaultIfEmpty()
    {
        $packageFile = new RootPackageFile();
        $package = new RootPackage($packageFile, '/path');

        $this->assertSame('__root__', $package->getName());
    }
}
