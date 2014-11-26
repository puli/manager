<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\InstallFile\Reader;

use Puli\RepositoryManager\Package\InstallFile\PackageDescriptor;
use Puli\RepositoryManager\Package\InstallFile\Reader\PackagesJsonReader;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackagesJsonReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PackagesJsonReader
     */
    private $reader;

    protected function setUp()
    {
        $this->reader = new PackagesJsonReader();
    }

    public function testReadConfig()
    {
        $package1 = new PackageDescriptor('/path/to/package1');
        $package2 = new PackageDescriptor('/path/to/package2');
        $package2->setNew(false);

        $config = $this->reader->readInstallFile(__DIR__.'/Fixtures/config.json');

        $this->assertInstanceOf('Puli\RepositoryManager\Package\InstallFile\InstallFile', $config);
        $this->assertSame(__DIR__.'/Fixtures/config.json', $config->getPath());
        $this->assertEquals(array($package1, $package2), $config->getPackageDescriptors());
    }

    /**
     * @expectedException \Puli\RepositoryManager\InvalidConfigException
     * @expectedExceptionMessage invalid.json
     */
    public function testReadConfigValidatesSchema()
    {
        $this->reader->readInstallFile(__DIR__.'/Fixtures/invalid.json');
    }

    /**
     * @expectedException \Puli\RepositoryManager\FileNotFoundException
     * @expectedExceptionMessage bogus.json
     */
    public function testReadConfigFailsIfNotFound()
    {
        $this->reader->readInstallFile('bogus.json');
    }

    /**
     * @expectedException \Puli\RepositoryManager\InvalidConfigException
     * @expectedExceptionMessage win-1258.json
     */
    public function testReadConfigFailsIfDecodingNotPossible()
    {
        $this->reader->readInstallFile(__DIR__.'/Fixtures/win-1258.json');
    }
}
