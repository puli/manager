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

use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Config\ConfigFile;
use Puli\Manager\Config\ConfigJsonSerializer;
use Puli\Manager\Tests\JsonTestCase;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigJsonSerializerTest extends JsonTestCase
{
    const CONFIG_JSON = <<<JSON
{
    "puli-dir": "puli-dir",
    "factory": {
        "out": {
            "class": "Puli\\\\MyFactory",
            "file": "{\$puli-dir}/MyFactory.php"
        }
    },
    "repository": {
        "type": "my-type",
        "path": "{\$puli-dir}/my-repo"
    },
    "discovery": {
        "store": {
            "type": "my-store-type"
        }
    }
}

JSON;

    /**
     * @var ConfigJsonSerializer
     */
    private $serializer;

    protected function setUp()
    {
        $this->serializer = new ConfigJsonSerializer();
    }

    public function testSerializeConfig()
    {
        $configFile = new ConfigFile();
        $configFile->getConfig()->merge(array(
            Config::PULI_DIR => 'puli-dir',
            Config::FACTORY_OUT_CLASS => 'Puli\MyFactory',
            Config::FACTORY_OUT_FILE => '{$puli-dir}/MyFactory.php',
            Config::REPOSITORY_TYPE => 'my-type',
            Config::REPOSITORY_PATH => '{$puli-dir}/my-repo',
            Config::DISCOVERY_STORE_TYPE => 'my-store-type',
        ));

        $this->assertJsonEquals(self::CONFIG_JSON, $this->serializer->serializeConfigFile($configFile));
    }

    public function testSerializeEmptyConfig()
    {
        $configFile = new ConfigFile();

        $this->assertJsonEquals("{}\n", $this->serializer->serializeConfigFile($configFile));
    }

    public function testUnserializeConfigFile()
    {
        $configFile = $this->serializer->unserializeConfigFile(self::CONFIG_JSON, '/path');

        $this->assertInstanceOf('Puli\Manager\Api\Config\ConfigFile', $configFile);
        $this->assertSame('/path', $configFile->getPath());

        $config = $configFile->getConfig();
        $this->assertSame('puli-dir', $config->get(Config::PULI_DIR));
        $this->assertSame('Puli\MyFactory', $config->get(Config::FACTORY_OUT_CLASS));
        $this->assertSame('puli-dir/MyFactory.php', $config->get(Config::FACTORY_OUT_FILE));
        $this->assertSame('my-type', $config->get(Config::REPOSITORY_TYPE));
        $this->assertSame('puli-dir/my-repo', $config->get(Config::REPOSITORY_PATH));
        $this->assertSame('my-store-type', $config->get(Config::DISCOVERY_STORE_TYPE));
    }

    public function testUnserializeConfigFileWithEmptyPath()
    {
        $configFile = $this->serializer->unserializeConfigFile(self::CONFIG_JSON);

        $this->assertNull($configFile->getPath());
    }

    public function testUnserializeMinimalConfigFile()
    {
        $configFile = $this->serializer->unserializeConfigFile("{}\n");

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

    public function testUnserializeMinimalConfigFileWithBaseConfig()
    {
        $baseConfig = new Config();
        $configFile = $this->serializer->unserializeConfigFile("{}\n", null, $baseConfig);
        $config = $configFile->getConfig();

        $this->assertNotSame($baseConfig, $config);

        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
        $this->assertNull($config->get(Config::PULI_DIR, null, false));
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     * @expectedExceptionMessage /path/to/win-1258.json
     */
    public function testUnserializeConfigFileFailsIfDecodingNotPossible()
    {
        if (defined('JSON_C_VERSION')) {
            $this->markTestSkipped('This error is not reported when using JSONC.');
        }

        $this->serializer->unserializeConfigFile(__DIR__.'/Fixtures/win-1258.json', '/path/to/win-1258.json');
    }
}
