<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Config;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Config\ConfigJsonReader;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigJsonReaderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var ConfigJsonReader
     */
    private $reader;

    protected function setUp()
    {
        $this->reader = new ConfigJsonReader();
    }

    public function testReadConfigFile()
    {
        $configFile = $this->reader->readConfigFile(__DIR__.'/Fixtures/config.json');

        $this->assertInstanceOf('Puli\Manager\Api\Config\ConfigFile', $configFile);
        $this->assertSame(__DIR__.'/Fixtures/config.json', $configFile->getPath());

        $config = $configFile->getConfig();
        $this->assertSame('puli-dir', $config->get(Config::PULI_DIR));
        $this->assertSame('Puli\MyFactory', $config->get(Config::FACTORY_OUT_CLASS));
        $this->assertSame('puli-dir/MyFactory.php', $config->get(Config::FACTORY_OUT_FILE));
        $this->assertSame('my-type', $config->get(Config::REPOSITORY_TYPE));
        $this->assertSame('puli-dir/my-repo', $config->get(Config::REPOSITORY_PATH));
        $this->assertSame('my-store-type', $config->get(Config::DISCOVERY_STORE_TYPE));
    }

    public function testReadMinimalConfigFile()
    {
        $configFile = $this->reader->readConfigFile(__DIR__.'/Fixtures/minimal.json');

        $this->assertInstanceOf('Puli\Manager\Api\Config\ConfigFile', $configFile);

        // default values
        $config = $configFile->getConfig();
        $this->assertNull($config->get(Config::PULI_DIR));
        $this->assertNull($config->get(Config::FACTORY_OUT_CLASS));
        $this->assertNull($config->get(Config::FACTORY_OUT_FILE));
        $this->assertNull($config->get(Config::REPOSITORY_TYPE));
        $this->assertNull($config->get(Config::REPOSITORY_PATH));
        $this->assertNull($config->get(Config::DISCOVERY_STORE_TYPE));
    }

    public function testReadMinimalConfigFileWithBaseConfig()
    {
        $baseConfig = new Config();
        $configFile = $this->reader->readConfigFile(__DIR__.'/Fixtures/minimal.json', $baseConfig);
        $config = $configFile->getConfig();

        $this->assertNotSame($baseConfig, $config);

        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
        $this->assertNull($config->get(Config::PULI_DIR, null, false));
    }

    /**
     * @expectedException \Puli\Manager\Api\FileNotFoundException
     * @expectedExceptionMessage bogus.json
     */
    public function testReadConfigFileFailsIfNotFound()
    {
        $this->reader->readConfigFile('bogus.json');
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     * @expectedExceptionMessage win-1258.json
     */
    public function testReadConfigFileFailsIfDecodingNotPossible()
    {
        if (false !== strpos(PHP_VERSION, 'ubuntu')) {
            $this->markTestSkipped('This error is not reported on PHP versions compiled for Ubuntu.');

            return;
        }

        $this->reader->readConfigFile(__DIR__.'/Fixtures/win-1258.json');
    }
}
