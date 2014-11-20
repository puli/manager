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

    protected function setUp()
    {
        $this->writer = new ConfigJsonWriter();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'PackageJsonWriterTest');
    }

    protected function tearDown()
    {
        unlink($this->tempFile);
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
}
