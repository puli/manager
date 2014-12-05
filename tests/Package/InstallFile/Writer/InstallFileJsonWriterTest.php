<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\InstallFile\Writer;

use Puli\RepositoryManager\Package\InstallFile\InstallFile;
use Puli\RepositoryManager\Package\InstallFile\PackageMetadata;
use Puli\RepositoryManager\Package\InstallFile\Writer\InstallFileJsonWriter;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallFileJsonWriterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var InstallFileJsonWriter
     */
    private $writer;

    private $tempFile;

    private $tempDir;

    protected function setUp()
    {
        $this->writer = new InstallFileJsonWriter();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'InstallFileJsonWriterTest');
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/InstallFileJsonWriterTest_temp'.rand(10000, 99999), 0777, true)) {}
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempFile);
        $filesystem->remove($this->tempDir);
    }

    public function testWriteConfig()
    {
        $metadata1 = new PackageMetadata('/path/to/package1');
        $metadata1->setInstaller('Composer');
        $metadata2 = new PackageMetadata('/path/to/package2');
        $metadata2->setName('package2');

        $config = new InstallFile();
        $config->addPackageMetadata($metadata1);
        $config->addPackageMetadata($metadata2);

        $this->writer->writeInstallFile($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            $this->assertFileEquals(__DIR__.'/Fixtures/config.json', $this->tempFile);
        } else {
            $this->assertFileEquals(__DIR__.'/Fixtures/config-ugly.json', $this->tempFile);
        }
    }

    public function testWriteEmptyConfig()
    {
        $config = new InstallFile();

        $this->writer->writeInstallFile($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/empty.json', $this->tempFile);
    }

    public function testCreateMissingDirectoriesOnDemand()
    {
        $config = new InstallFile();
        $file = $this->tempDir.'/new/config.json';

        $this->writer->writeInstallFile($config, $file);

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
        $this->writer->writeInstallFile(new InstallFile(), $invalidPath);
    }
}
