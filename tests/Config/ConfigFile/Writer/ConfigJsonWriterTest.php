<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Config\ConfigFile\Writer;

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFile;
use Puli\RepositoryManager\Config\ConfigFile\Writer\ConfigJsonWriter;
use Puli\RepositoryManager\Tests\JsonWriterTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigJsonWriterTest extends JsonWriterTestCase
{
    /**
     * @var ConfigJsonWriter
     */
    private $writer;

    private $tempFile;

    private $tempDir;

    protected function setUp()
    {
        $this->writer = new ConfigJsonWriter();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'ConfigJsonWriterTest');
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/ConfigJsonWriterTest_temp'.rand(10000, 99999), 0777, true)) {}
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempFile);
        $filesystem->remove($this->tempDir);
    }

    public function testWriteConfig()
    {
        $configFile = new ConfigFile();
        $configFile->getConfig()->merge(array(
            Config::PULI_DIR => 'puli-dir',
            Config::REGISTRY_CLASS => 'Puli\MyServiceRegistry',
            Config::REGISTRY_FILE => '{$puli-dir}/MyServiceRegistry.php',
            Config::REPO_TYPE => 'my-type',
            Config::REPO_STORAGE_DIR => '{$puli-dir}/my-repo',
            Config::REPO_VERSION_STORE_TYPE => 'my-store-type',
        ));

        $this->writer->writeConfigFile($configFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/config.json', $this->tempFile);
    }

    public function testWriteEmptyConfig()
    {
        $configFile = new ConfigFile();

        $this->writer->writeConfigFile($configFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/empty.json', $this->tempFile);
    }

    public function testCreateMissingDirectoriesOnDemand()
    {
        $configFile = new ConfigFile();
        $file = $this->tempDir.'/new/config.json';

        $this->writer->writeConfigFile($configFile, $file);

        $this->assertFileExists($file);
        $this->assertFileEquals(__DIR__.'/Fixtures/empty.json', $file);
    }

    public function provideInvalidPaths()
    {
        return array(
            array(null),
            array(''),
            array('/'),
        );
    }

    /**
     * @dataProvider provideInvalidPaths
     * @expectedException \Puli\RepositoryManager\IOException
     */
    public function testWriteConfigExpectsValidPath($invalidPath)
    {
        $this->writer->writeConfigFile(new ConfigFile(), $invalidPath);
    }
}
