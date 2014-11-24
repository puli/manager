<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Package\Config\Writer;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\ResourceDescriptor;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\Config\TagDescriptor;
use Puli\PackageManager\Package\Config\Writer\PackageJsonWriter;
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
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-manager/PackageJsonWriterTest_temp'.rand(10000, 99999), 0777, true)) {}
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempFile);
        $filesystem->remove($this->tempDir);
    }

    public function testWriteConfig()
    {
        $config = new PackageConfig();
        $config->setPackageName('my/application');
        $config->addResourceDescriptor(new ResourceDescriptor('/app', 'res'));
        $config->addTagDescriptor(new TagDescriptor('/app/config*.yml', 'config'));
        $config->setOverriddenPackages('acme/blog');

        $this->writer->writePackageConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/full.json', $this->tempFile);
    }

    public function testWriteRootConfig()
    {
        $globalConfig = new GlobalConfig();
        $config = new RootPackageConfig($globalConfig);
        $config->setPackageName('my/application');
        $config->addResourceDescriptor(new ResourceDescriptor('/app', 'res'));
        $config->addTagDescriptor(new TagDescriptor('/app/config*.yml', 'config'));
        $config->setOverriddenPackages('acme/blog');
        $config->setPackageOrder(array('acme/blog-extension1', 'acme/blog-extension2'));
        $config->setPackageRepositoryConfig('packages.json');
        $config->setGeneratedResourceRepository('resource-repository.php');
        $config->setResourceRepositoryCache('cache');
        $config->addPluginClass('Puli\PackageManager\Tests\Config\Fixtures\TestPlugin');

        $this->writer->writePackageConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/full-root.json', $this->tempFile);
    }

    public function testWriteMinimalRootConfig()
    {
        $globalConfig = new GlobalConfig();
        $config = new RootPackageConfig($globalConfig);

        $this->writer->writePackageConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/minimal.json', $this->tempFile);
    }

    public function testWriteRootConfigDoesNotWriteGlobalValues()
    {
        $globalConfig = new GlobalConfig();
        $globalConfig->setPackageRepositoryConfig('packages.json');
        $globalConfig->setGeneratedResourceRepository('resource-repository.php');
        $globalConfig->setResourceRepositoryCache('cache');
        $globalConfig->addPluginClass('Puli\PackageManager\Tests\Config\Fixtures\TestPlugin');
        $config = new RootPackageConfig($globalConfig);

        $this->writer->writePackageConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/minimal.json', $this->tempFile);
    }

    public function testWriteResourcesWithMultipleLocalPaths()
    {
        $config = new PackageConfig();
        $config->setPackageName('my/application');
        $config->addResourceDescriptor(new ResourceDescriptor('/app', array('res', 'assets')));

        $this->writer->writePackageConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/multi-resources.json', $this->tempFile);
    }

    public function testWriteTagsWithMultipleTags()
    {
        $config = new PackageConfig();
        $config->setPackageName('my/application');
        $config->addTagDescriptor(new TagDescriptor('/app/config*.yml', array('yaml', 'config')));

        $this->writer->writePackageConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/multi-tags.json', $this->tempFile);
    }

    public function testWriteMultipleOverriddenPackages()
    {
        $config = new PackageConfig();
        $config->setPackageName('my/application');
        $config->setOverriddenPackages(array('acme/blog1', 'acme/blog2'));

        $this->writer->writePackageConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/multi-overrides.json', $this->tempFile);
    }

    public function testCreateMissingDirectoriesOnDemand()
    {
        $config = new PackageConfig();
        $file = $this->tempDir.'/new/config.json';

        $this->writer->writePackageConfig($config, $file);

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
     * @expectedException \Puli\PackageManager\IOException
     */
    public function testWriteConfigExpectsValidPath($invalidPath)
    {
        $config = new PackageConfig();
        $config->setPackageName('my/application');

        $this->writer->writePackageConfig($config, $invalidPath);
    }
}
