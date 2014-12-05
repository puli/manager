<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\InstallFile\Reader;

use Puli\RepositoryManager\Package\InstallFile\PackageMetadata;
use Puli\RepositoryManager\Package\InstallFile\Reader\InstallFileJsonReader;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallFileJsonReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var InstallFileJsonReader
     */
    private $reader;

    protected function setUp()
    {
        $this->reader = new InstallFileJsonReader();
    }

    public function testReadConfig()
    {
        $metadata1 = new PackageMetadata('/path/to/package1');
        $metadata1->setInstaller('Composer');
        $metadata2 = new PackageMetadata('/path/to/package2');
        $metadata2->setName('package2');

        $config = $this->reader->readInstallFile(__DIR__.'/Fixtures/config.json');

        $this->assertInstanceOf('Puli\RepositoryManager\Package\InstallFile\InstallFile', $config);
        $this->assertSame(__DIR__.'/Fixtures/config.json', $config->getPath());
        $this->assertEquals(array($metadata1, $metadata2), $config->listPackageMetadata());
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
