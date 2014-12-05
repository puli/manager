<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\PackageFile\Writer;

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\ResourceDescriptor;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\PackageFile\TagDescriptor;
use Puli\RepositoryManager\Package\PackageFile\Writer\PackageJsonWriter;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageJsonWriterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PackageJsonWriter
     */
    private $writer;

    private $tempFile;

    private $tempDir;

    protected function setUp()
    {
        $this->writer = new PackageJsonWriter();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'PackageJsonWriterTest');

        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/PackageJsonWriterTest_temp'.rand(10000, 99999), 0777, true)) {}
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempFile);
        $filesystem->remove($this->tempDir);
    }

    public function testWritePackageFile()
    {
        $packageFile = new PackageFile();
        $packageFile->setPackageName('my/application');
        $packageFile->addResourceDescriptor(new ResourceDescriptor('/app', 'res'));
        $packageFile->addTagDescriptor(new TagDescriptor('/app/config*.yml', 'config'));
        $packageFile->setOverriddenPackages('acme/blog');

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            $this->assertFileEquals(__DIR__.'/Fixtures/full.json', $this->tempFile);
        } else {
            $this->assertFileEquals(__DIR__.'/Fixtures/full-ugly.json', $this->tempFile);
        }
    }

    public function testWriteRootPackageFile()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, null, $baseConfig);
        $packageFile->setPackageName('my/application');
        $packageFile->addResourceDescriptor(new ResourceDescriptor('/app', 'res'));
        $packageFile->addTagDescriptor(new TagDescriptor('/app/config*.yml', 'config'));
        $packageFile->setOverriddenPackages('acme/blog');
        $packageFile->setPackageOrder(array('acme/blog-extension1', 'acme/blog-extension2'));
        $packageFile->addPluginClass('Puli\RepositoryManager\Tests\Package\PackageFile\Fixtures\TestPlugin');
        $packageFile->getConfig()->merge(array(
            Config::PULI_DIR => 'puli-dir',
            Config::INSTALL_FILE => '{$puli-dir}/my-install-file.json',
            Config::DUMP_DIR => '{$puli-dir}/my-repo',
            Config::READ_REPO => '{$puli-dir}/my-repository.php',
            Config::WRITE_REPO => '{$puli-dir}/my-repository-dump.php',
        ));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            $this->assertFileEquals(__DIR__.'/Fixtures/full-root.json', $this->tempFile);
        } else {
            $this->assertFileEquals(__DIR__.'/Fixtures/full-root-ugly.json', $this->tempFile);
        }
    }

    public function testWriteMinimalRootPackageFile()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, null, $baseConfig);

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/minimal.json', $this->tempFile);
    }

    public function testWriteRootPackageFileDoesNotWriteBaseConfigValues()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'puli-dir');
        $packageFile = new RootPackageFile(null, null, $baseConfig);

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/minimal.json', $this->tempFile);
    }

    public function testWriteResourcesWithMultipleLocalPaths()
    {
        $packageFile = new PackageFile();
        $packageFile->setPackageName('my/application');
        $packageFile->addResourceDescriptor(new ResourceDescriptor('/app', array('res', 'assets')));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            $this->assertFileEquals(__DIR__.'/Fixtures/multi-resources.json', $this->tempFile);
        } else {
            $this->assertFileEquals(__DIR__.'/Fixtures/multi-resources-ugly.json', $this->tempFile);
        }
    }

    public function testWriteTagsWithMultipleTags()
    {
        $packageFile = new PackageFile();
        $packageFile->setPackageName('my/application');
        $packageFile->addTagDescriptor(new TagDescriptor('/app/config*.yml', array('yaml', 'config')));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            $this->assertFileEquals(__DIR__.'/Fixtures/multi-tags.json', $this->tempFile);
        } else {
            $this->assertFileEquals(__DIR__.'/Fixtures/multi-tags-ugly.json', $this->tempFile);
        }
    }

    public function testWriteMultipleOverriddenPackages()
    {
        $packageFile = new PackageFile();
        $packageFile->setPackageName('my/application');
        $packageFile->setOverriddenPackages(array('acme/blog1', 'acme/blog2'));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            $this->assertFileEquals(__DIR__.'/Fixtures/multi-overrides.json', $this->tempFile);
        } else {
            $this->assertFileEquals(__DIR__.'/Fixtures/multi-overrides-ugly.json', $this->tempFile);
        }
    }

    public function testCreateMissingDirectoriesOnDemand()
    {
        $packageFile = new PackageFile();
        $file = $this->tempDir.'/new/config.json';

        $this->writer->writePackageFile($packageFile, $file);

        $this->assertFileExists($file);
        $this->assertFileEquals(__DIR__.'/Fixtures/minimal.json', $file);
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
        $packageFile = new PackageFile();
        $packageFile->setPackageName('my/application');

        $this->writer->writePackageFile($packageFile, $invalidPath);
    }
}
