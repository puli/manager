<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Config\Writer;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Config\Writer\ConfigJsonWriter;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigJsonWriterTest extends \PHPUnit_Framework_TestCase
{
    const PLUGIN_CLASS = 'Puli\PackageManager\Tests\Config\Fixtures\TestPlugin';

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
        while (false === mkdir($this->tempDir = sys_get_temp_dir().'/puli-manager/ConfigJsonWriterTest_temp'.rand(10000, 99999), 0777, true)) {}
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempFile);
        $filesystem->remove($this->tempDir);
    }

    public function testWriteConfig()
    {
        $config = new GlobalConfig();
        $config->addPluginClass(self::PLUGIN_CLASS);

        $this->writer->writeGlobalConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/config.json', $this->tempFile);
    }

    public function testWriteEmptyConfig()
    {
        $config = new GlobalConfig();

        $this->writer->writeGlobalConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/empty.json', $this->tempFile);
    }

    public function testCreateMissingDirectoriesOnDemand()
    {
        $config = new GlobalConfig();
        $file = $this->tempDir.'/new/config.json';

        $this->writer->writeGlobalConfig($config, $file);

        $this->assertFileExists($file);
        $this->assertFileEquals(__DIR__.'/Fixtures/empty.json', $file);
    }
}
