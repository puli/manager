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
use Puli\RepositoryManager\Api\Package\InstallInfo;
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Api\Package\PackageFile;
use Puli\RepositoryManager\Api\Package\PackageState;
use RuntimeException;
use Webmozart\Expression\Expr;

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

    public function testEnabledIfFound()
    {
        $packageFile = new PackageFile('vendor/name');
        $package = new Package($packageFile, __DIR__);

        $this->assertSame(PackageState::ENABLED, $package->getState());
    }

    public function testNotFoundIfNotFound()
    {
        $packageFile = new PackageFile('vendor/name');
        $package = new Package($packageFile, __DIR__.'/foobar');

        $this->assertSame(PackageState::NOT_FOUND, $package->getState());
    }

    public function testNotLoadableIfLoadErrors()
    {
        $packageFile = new PackageFile('vendor/name');
        $package = new Package($packageFile, __DIR__, null, array(
            new RuntimeException('Could not load package'),
        ));

        $this->assertSame(PackageState::NOT_LOADABLE, $package->getState());
    }

    public function testMatch()
    {
        $packageFile = new PackageFile('vendor/name');
        $package = new Package($packageFile, __DIR__);

        $this->assertFalse($package->match(Expr::same(Package::NAME, 'foobar')));
        $this->assertTrue($package->match(Expr::same(Package::NAME, 'vendor/name')));

        $this->assertFalse($package->match(Expr::same(Package::INSTALL_PATH, '/path/foo')));
        $this->assertTrue($package->match(Expr::same(Package::INSTALL_PATH, __DIR__)));

        $this->assertFalse($package->match(Expr::same(Package::STATE, PackageState::NOT_LOADABLE)));
        $this->assertTrue($package->match(Expr::same(Package::STATE, PackageState::ENABLED)));

        $this->assertFalse($package->match(Expr::same(Package::INSTALLER, 'webmozart')));

        $installInfo = new InstallInfo('vendor/install-info', '/path');
        $installInfo->setInstallerName('webmozart');
        $packageWithInstallInfo = new Package($packageFile, __DIR__, $installInfo);

        $this->assertFalse($packageWithInstallInfo->match(Expr::same(Package::INSTALLER, 'foobar')));
        $this->assertTrue($packageWithInstallInfo->match(Expr::same(Package::INSTALLER, 'webmozart')));
    }
}
