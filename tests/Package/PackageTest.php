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

use Puli\RepositoryManager\Package\InstallFile\PackageMetadata;
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

    public function testUsePackageNameFromMetadata()
    {
        $packageFile = new PackageFile();
        $metadata = new PackageMetadata('/path');
        $metadata->setName('name');
        $package = new Package($packageFile, '/path', $metadata);

        $this->assertSame('name', $package->getName());
    }

    public function testPreferPackageNameFromMetadata()
    {
        $packageFile = new PackageFile('package-file');
        $metadata = new PackageMetadata('/path');
        $metadata->setName('metadata');
        $package = new Package($packageFile, '/path', $metadata);

        $this->assertSame('metadata', $package->getName());
    }

    public function testNameIsNullIfNoneSet()
    {
        $packageFile = new PackageFile();
        $metadata = new PackageMetadata('/path');
        $package = new Package($packageFile, '/path', $metadata);

        $this->assertNull($package->getName());
    }

    public function testNameIsNullIfNoneSetAndNoMetadataGiven()
    {
        $packageFile = new PackageFile();
        $package = new Package($packageFile, '/path');

        $this->assertNull($package->getName());
    }
}
