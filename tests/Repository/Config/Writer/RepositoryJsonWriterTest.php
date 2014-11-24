<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Repository\Config\Writer;

use Puli\PackageManager\Repository\Config\PackageDescriptor;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;
use Puli\PackageManager\Repository\Config\Writer\RepositoryJsonWriter;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryJsonWriterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RepositoryJsonWriter
     */
    private $writer;

    private $tempFile;

    private $tempDir;

    protected function setUp()
    {
        $this->writer = new RepositoryJsonWriter();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'RepositoryJsonWriterTest');
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-manager/RepositoryJsonWriterTest_temp'.rand(10000, 99999), 0777, true)) {}
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempFile);
        $filesystem->remove($this->tempDir);
    }

    public function testWriteConfig()
    {
        $config = new PackageRepositoryConfig();
        $config->addPackageDescriptor(new PackageDescriptor('/path/to/package1', true));
        $config->addPackageDescriptor(new PackageDescriptor('/path/to/package2', false));

        $this->writer->writeRepositoryConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/config.json', $this->tempFile);
    }

    public function testWriteEmptyConfig()
    {
        $config = new PackageRepositoryConfig();

        $this->writer->writeRepositoryConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/empty.json', $this->tempFile);
    }

    public function testCreateMissingDirectoriesOnDemand()
    {
        $config = new PackageRepositoryConfig();
        $file = $this->tempDir.'/new/config.json';

        $this->writer->writeRepositoryConfig($config, $file);

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
     * @expectedException \Puli\PackageManager\IOException
     */
    public function testWriteConfigExpectsValidPath($invalidPath)
    {
        $this->writer->writeRepositoryConfig(new PackageRepositoryConfig(), $invalidPath);
    }
}
