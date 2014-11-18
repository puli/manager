<?php

/*
 * This file is part of the Puli Packages package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Packages\Tests\Repository\Config\Reader;

use Puli\Packages\Repository\Config\PackageDefinition;
use Puli\Packages\Repository\Config\Reader\RepositoryJsonReader;

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
        $package1 = new PackageDefinition('/path/to/package1');
        $package2 = new PackageDefinition('/path/to/package2');
        $package2->setNew(false);

        $config = $this->reader->readRepositoryConfig(__DIR__.'/Fixtures/config.json');

        $this->assertInstanceOf('Puli\Packages\Repository\Config\RepositoryConfig', $config);
        $this->assertEquals(array($package1, $package2), $config->getPackageDefinitions());
    }

    /**
     * @expectedException \Puli\Packages\InvalidConfigException
     */
    public function testReadConfigValidatesSchema()
    {
        $this->reader->readRepositoryConfig(__DIR__.'/Fixtures/invalid.json');
    }

    /**
     * @expectedException \Puli\Packages\FileNotFoundException
     */
    public function testReadConfigFailsIfNotFound()
    {
        $this->reader->readRepositoryConfig('bogus.json');
    }
}
