<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Package;

use Exception;
use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\PackageState;
use RuntimeException;
use Webmozart\Expression\Expr;

/**
 * @since  1.0
 *
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

    public function testCreatePackageWithoutPackageFileNorInstallInfo()
    {
        $package = new Package(null, '/path', null, array(new Exception()));

        $this->assertNull($package->getName());
    }

    public function testMatch()
    {
        $packageFile = new PackageFile('vendor/name');
        $package = new Package($packageFile, __DIR__);

        $this->assertFalse($package->match(Expr::same('foobar', Package::NAME)));
        $this->assertTrue($package->match(Expr::same('vendor/name', Package::NAME)));

        $this->assertFalse($package->match(Expr::same('/path/foo', Package::INSTALL_PATH)));
        $this->assertTrue($package->match(Expr::same(__DIR__, Package::INSTALL_PATH)));

        $this->assertFalse($package->match(Expr::same(PackageState::NOT_LOADABLE, Package::STATE)));
        $this->assertTrue($package->match(Expr::same(PackageState::ENABLED, Package::STATE)));

        $this->assertFalse($package->match(Expr::same('webmozart', Package::INSTALLER)));

        // Packages without install info (= the root package) are assumed to be
        // installed in the production environment
        $this->assertTrue($package->match(Expr::same(Environment::PROD, Package::ENVIRONMENT)));

        $installInfo = new InstallInfo('vendor/install-info', '/path');
        $installInfo->setInstallerName('webmozart');
        $packageWithInstallInfo = new Package($packageFile, __DIR__, $installInfo);

        $this->assertFalse($packageWithInstallInfo->match(Expr::same('foobar', Package::INSTALLER)));
        $this->assertTrue($packageWithInstallInfo->match(Expr::same('webmozart', Package::INSTALLER)));
        $this->assertTrue($packageWithInstallInfo->match(Expr::notsame(Environment::DEV, Package::ENVIRONMENT)));

        $installInfo2 = new InstallInfo('vendor/install-info', '/path');
        $installInfo2->setEnvironment(Environment::DEV);
        $packageWithInstallInfo2 = new Package($packageFile, __DIR__, $installInfo2);
        $this->assertTrue($packageWithInstallInfo2->match(Expr::same(Environment::DEV, Package::ENVIRONMENT)));
    }
}
