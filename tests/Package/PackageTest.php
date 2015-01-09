<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageTest extends PHPUnit_Framework_TestCase
{
    public function testUsePackageNameFromPackageFile()
    {
        $packageFile = new PackageFile('vendor/name');
        $package = new Package($packageFile, '/path');

        $this->assertSame('vendor/name', $package->getName());
    }

    public function testUsePackageNameFromInstallInfo()
    {
        $packageFile = new PackageFile();
        $installInfo = new InstallInfo('vendor/name', '/path');
        $package = new Package($packageFile, '/path', $installInfo);

        $this->assertSame('vendor/name', $package->getName());
    }

    public function testPreferPackageNameFromInstallInfo()
    {
        $packageFile = new PackageFile('vendor/package-file');
        $installInfo = new InstallInfo('vendor/install-info', '/path');
        $package = new Package($packageFile, '/path', $installInfo);

        $this->assertSame('vendor/install-info', $package->getName());
    }

    public function testNameIsNullIfNoneSetAndNoInstallInfoGiven()
    {
        $packageFile = new PackageFile();
        $package = new Package($packageFile, '/path');

        $this->assertNull($package->getName());
    }
}
