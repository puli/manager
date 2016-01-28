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
use Puli\Manager\Api\Config\ConfigFile;
use Puli\Manager\Config\ConfigFileConverter;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigFileConverterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var ConfigFileConverter
     */
    private $converter;

    protected function setUp()
    {
        $this->converter = new ConfigFileConverter();
    }

    public function testToJson()
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

        $jsonData = (object) array(
            'puli-dir' => 'puli-dir',
            'factory' => (object) array(
                'out' => (object) array(
                    'class' => 'Puli\MyFactory',
                    'file' => '{$puli-dir}/MyFactory.php',
                ),
            ),
            'repository' => (object) array(
                'type' => 'my-type',
                'path' => '{$puli-dir}/my-repo',
            ),
            'discovery' => (object) array(
                'store' => (object) array(
                    'type' => 'my-store-type',
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($configFile));
    }

    public function testToJsonEmptyConfig()
    {
        $configFile = new ConfigFile();

        $this->assertEquals((object) array(), $this->converter->toJson($configFile));
    }

    public function testFromJson()
    {
        $jsonData = (object) array(
            'puli-dir' => 'puli-dir',
            'factory' => (object) array(
                'out' => (object) array(
                    'class' => 'Puli\MyFactory',
                    'file' => '{$puli-dir}/MyFactory.php',
                ),
            ),
            'repository' => (object) array(
                'type' => 'my-type',
                'path' => '{$puli-dir}/my-repo',
            ),
            'discovery' => (object) array(
                'store' => (object) array(
                    'type' => 'my-store-type',
                ),
            ),
        );

        $configFile = $this->converter->fromJson($jsonData, array(
            'path' => '/path',
        ));

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

    public function testFromJsonWithEmptyPath()
    {
        $configFile = $this->converter->fromJson((object) array());

        $this->assertNull($configFile->getPath());
    }

    public function testFromJsonMinimal()
    {
        $configFile = $this->converter->fromJson((object) array(), array(
            'path' => '/path',
        ));

        $this->assertInstanceOf('Puli\Manager\Api\Config\ConfigFile', $configFile);
        $this->assertSame('/path', $configFile->getPath());

        // default values
        $config = $configFile->getConfig();
        $this->assertNull($config->get(Config::PULI_DIR));
        $this->assertNull($config->get(Config::FACTORY_OUT_CLASS));
        $this->assertNull($config->get(Config::FACTORY_OUT_FILE));
        $this->assertNull($config->get(Config::REPOSITORY_TYPE));
        $this->assertNull($config->get(Config::REPOSITORY_PATH));
        $this->assertNull($config->get(Config::DISCOVERY_STORE_TYPE));
    }

    public function testFromJsonWithBaseConfig()
    {
        $baseConfig = new Config();
        $configFile = $this->converter->fromJson((object) array(), array(
            'path' => '/path',
            'baseConfig' => $baseConfig,
        ));
        $config = $configFile->getConfig();

        $this->assertNotSame($baseConfig, $config);

        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
        $this->assertNull($config->get(Config::PULI_DIR, null, false));
    }
}
