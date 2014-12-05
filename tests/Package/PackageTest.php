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

use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageTest extends \PHPUnit_Framework_TestCase
{
    public function testUsePackageNameFromPackageFile()
    {
        $packageFile = new PackageFile('name');
        $package = new Package($packageFile, '/path');

        $this->assertSame('name', $package->getName());
    }

    public function testUsePackageNameFromInstallInfo()
    {
        $packageFile = new PackageFile();
        $installInfo = new InstallInfo('/path');
        $installInfo->setPackageName('name');
        $package = new Package($packageFile, '/path', $installInfo);

        $this->assertSame('name', $package->getName());
    }

    public function testPreferPackageNameFromInstallInfo()
    {
        $packageFile = new PackageFile('package-file');
        $installInfo = new InstallInfo('/path');
        $installInfo->setPackageName('installInfo');
        $package = new Package($packageFile, '/path', $installInfo);

        $this->assertSame('installInfo', $package->getName());
    }

    public function testNameIsNullIfNoneSet()
    {
        $packageFile = new PackageFile();
        $installInfo = new InstallInfo('/path');
        $package = new Package($packageFile, '/path', $installInfo);

        $this->assertNull($package->getName());
    }

    public function testNameIsNullIfNoneSetAndNoInstallInfoGiven()
    {
        $packageFile = new PackageFile();
        $package = new Package($packageFile, '/path');

        $this->assertNull($package->getName());
    }
}
