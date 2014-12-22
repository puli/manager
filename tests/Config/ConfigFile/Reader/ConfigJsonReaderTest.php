<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Config\ConfigFile\Reader;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\Reader\ConfigJsonReader;

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

        $this->assertInstanceOf('Puli\RepositoryManager\Config\ConfigFile\ConfigFile', $configFile);
        $this->assertSame(__DIR__.'/Fixtures/config.json', $configFile->getPath());

        $config = $configFile->getConfig();
        $this->assertSame('puli-dir', $config->get(Config::PULI_DIR));
        $this->assertSame('puli-dir/my-repository.php', $config->get(Config::READ_REPO));
        $this->assertSame('puli-dir/my-repo', $config->get(Config::DUMP_DIR));
        $this->assertSame('puli-dir/my-repository-dump.php', $config->get(Config::WRITE_REPO));
        $this->assertSame('php', $config->get(Config::DISCOVERY_STORAGE));
        $this->assertSame('puli-dir/bar', $config->get('discovery-storage-foo'));
    }

    public function testReadMinimalConfigFile()
    {
        $configFile = $this->reader->readConfigFile(__DIR__.'/Fixtures/minimal.json');

        $this->assertInstanceOf('Puli\RepositoryManager\Config\ConfigFile\ConfigFile', $configFile);

        // default values
        $config = $configFile->getConfig();
        $this->assertNull($config->get(Config::PULI_DIR));
        $this->assertNull($config->get(Config::READ_REPO));
        $this->assertNull($config->get(Config::DUMP_DIR));
        $this->assertNull($config->get(Config::WRITE_REPO));
        $this->assertNull($config->get(Config::DISCOVERY_STORAGE));
        $this->assertNull($config->get('discovery-storage-foo'));
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
     * @expectedException \Puli\RepositoryManager\FileNotFoundException
     * @expectedExceptionMessage bogus.json
     */
    public function testReadConfigFileFailsIfNotFound()
    {
        $this->reader->readConfigFile('bogus.json');
    }

    /**
     * @expectedException \Puli\RepositoryManager\InvalidConfigException
     * @expectedExceptionMessage win-1258.json
     */
    public function testReadConfigFileFailsIfDecodingNotPossible()
    {
        $this->reader->readConfigFile(__DIR__.'/Fixtures/win-1258.json');
    }
}
