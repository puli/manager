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

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\Reader\ConfigJsonReader;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigJsonReaderTest extends \PHPUnit_Framework_TestCase
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
        $this->assertSame('puli-dir/my-install-file.json', $config->get(Config::INSTALL_FILE));
        $this->assertSame('puli-dir/my-repository.php', $config->get(Config::REPO_FILE));
        $this->assertSame('puli-dir/my-repo', $config->get(Config::REPO_DUMP_DIR));
        $this->assertSame('puli-dir/my-repository-dump.php', $config->get(Config::REPO_DUMP_FILE));
    }

    public function testReadMinimalConfigFile()
    {
        $configFile = $this->reader->readConfigFile(__DIR__.'/Fixtures/minimal.json');

        $this->assertInstanceOf('Puli\RepositoryManager\Config\ConfigFile\ConfigFile', $configFile);

        // default values
        $config = $configFile->getConfig();
        $this->assertSame('.puli', $config->get(Config::PULI_DIR));
        $this->assertSame('.puli/install-file.json', $config->get(Config::INSTALL_FILE));
        $this->assertSame('.puli/resource-repository.php', $config->get(Config::REPO_FILE));
        $this->assertSame('.puli/repo', $config->get(Config::REPO_DUMP_DIR));
        $this->assertSame('.puli/resource-repository.php', $config->get(Config::REPO_DUMP_FILE));
    }

    /**
     * @expectedException \Puli\RepositoryManager\InvalidConfigException
     * @expectedExceptionMessage invalid.json
     */
    public function testReadConfigFileValidatesSchema()
    {
        $this->reader->readConfigFile(__DIR__.'/Fixtures/invalid.json');
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
