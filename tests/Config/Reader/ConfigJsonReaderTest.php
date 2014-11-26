<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Config\Reader;

use Puli\PackageManager\Config\Reader\ConfigJsonReader;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigJsonReaderTest extends \PHPUnit_Framework_TestCase
{
    const PLUGIN_CLASS = 'Puli\PackageManager\Tests\Config\Fixtures\TestPlugin';

    /**
     * @var ConfigJsonReader
     */
    private $reader;

    protected function setUp()
    {
        $this->reader = new ConfigJsonReader();
    }

    public function testReadConfig()
    {
        $config = $this->reader->readGlobalConfig(__DIR__.'/Fixtures/config.json');

        $this->assertInstanceOf('Puli\PackageManager\Config\GlobalConfig', $config);
        $this->assertSame(__DIR__.'/Fixtures/config.json', $config->getPath());
        $this->assertSame(array(self::PLUGIN_CLASS), $config->getPluginClasses());

        // non-configurable values
        $this->assertNull($config->getInstallFile(false));
        $this->assertNull($config->getGeneratedResourceRepository(false));
        $this->assertNull($config->getResourceRepositoryCache(false));
    }

    public function testReadMinimalConfig()
    {
        $config = $this->reader->readGlobalConfig(__DIR__.'/Fixtures/minimal.json');

        $this->assertInstanceOf('Puli\PackageManager\Config\GlobalConfig', $config);
        $this->assertSame(array(), $config->getPluginClasses());

        // non-configurable values
        $this->assertNull($config->getInstallFile(false));
        $this->assertNull($config->getGeneratedResourceRepository(false));
        $this->assertNull($config->getResourceRepositoryCache(false));
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     * @expectedExceptionMessage invalid.json
     */
    public function testReadConfigValidatesSchema()
    {
        $this->reader->readGlobalConfig(__DIR__.'/Fixtures/invalid.json');
    }

    /**
     * @expectedException \Puli\PackageManager\FileNotFoundException
     * @expectedExceptionMessage bogus.json
     */
    public function testReadConfigFailsIfNotFound()
    {
        $this->reader->readGlobalConfig('bogus.json');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     * @expectedExceptionMessage win-1258.json
     */
    public function testReadConfigFailsIfDecodingNotPossible()
    {
        $this->reader->readGlobalConfig(__DIR__.'/Fixtures/win-1258.json');
    }
}
