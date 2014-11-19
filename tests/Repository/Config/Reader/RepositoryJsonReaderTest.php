<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Repository\Config\Reader;

use Puli\PackageManager\Repository\Config\PackageDescriptor;
use Puli\PackageManager\Repository\Config\Reader\RepositoryJsonReader;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryJsonReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RepositoryJsonReader
     */
    private $reader;

    protected function setUp()
    {
        $this->reader = new RepositoryJsonReader();
    }

    public function testReadConfig()
    {
        $package1 = new PackageDescriptor('/path/to/package1');
        $package2 = new PackageDescriptor('/path/to/package2');
        $package2->setNew(false);

        $config = $this->reader->readRepositoryConfig(__DIR__.'/Fixtures/config.json');

        $this->assertInstanceOf('Puli\PackageManager\Repository\Config\PackageRepositoryConfig', $config);
        $this->assertEquals(array($package1, $package2), $config->getPackageDescriptors());
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     * @expectedExceptionMessage invalid.json
     */
    public function testReadConfigValidatesSchema()
    {
        $this->reader->readRepositoryConfig(__DIR__.'/Fixtures/invalid.json');
    }

    /**
     * @expectedException \Puli\PackageManager\FileNotFoundException
     * @expectedExceptionMessage bogus.json
     */
    public function testReadConfigFailsIfNotFound()
    {
        $this->reader->readRepositoryConfig('bogus.json');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     * @expectedExceptionMessage win-1258.json
     */
    public function testReadConfigFailsIfDecodingNotPossible()
    {
        $this->reader->readRepositoryConfig(__DIR__.'/Fixtures/win-1258.json');
    }
}
