<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\Config\Writer;

use Puli\RepositoryManager\Config\GlobalConfig;
use Puli\RepositoryManager\Package\Config\PackageConfig;
use Puli\RepositoryManager\Package\Config\ResourceDescriptor;
use Puli\RepositoryManager\Package\Config\RootPackageConfig;
use Puli\RepositoryManager\Package\Config\TagDescriptor;
use Puli\RepositoryManager\Package\Config\Writer\PuliJsonWriter;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageJsonWriterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PuliJsonWriter
     */
    private $writer;

    private $tempFile;

    private $tempDir;

    protected function setUp()
    {
        $this->writer = new PuliJsonWriter();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'PackageJsonWriterTest');
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/PackageJsonWriterTest_temp'.rand(10000, 99999), 0777, true)) {}
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
        $config->setInstallFile('packages.json');
        $config->setGeneratedResourceRepository('resource-repository.php');
        $config->setResourceRepositoryCache('cache');
        $config->addPluginClass('Puli\RepositoryManager\Tests\Config\Fixtures\TestPlugin');

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
        $globalConfig->setInstallFile('packages.json');
        $globalConfig->setGeneratedResourceRepository('resource-repository.php');
        $globalConfig->setResourceRepositoryCache('cache');
        $globalConfig->addPluginClass('Puli\RepositoryManager\Tests\Config\Fixtures\TestPlugin');
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
     * @expectedException \Puli\RepositoryManager\IOException
     */
    public function testWriteConfigExpectsValidPath($invalidPath)
    {
        $config = new PackageConfig();
        $config->setPackageName('my/application');

        $this->writer->writePackageConfig($config, $invalidPath);
    }
}
