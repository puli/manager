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

use Puli\PackageManager\Event\JsonEvent;
use Puli\PackageManager\Event\PackageEvents;
use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\ResourceDescriptor;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\Config\TagDescriptor;
use Puli\PackageManager\Package\Config\Writer\PackageJsonWriter;
use Symfony\Component\EventDispatcher\EventDispatcher;

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

    protected function setUp()
    {
        $this->writer = new PackageJsonWriter();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'PackageJsonWriterTest');
    }

    protected function tearDown()
    {
        unlink($this->tempFile);
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
        $config = new RootPackageConfig();
        $config->setPackageName('my/application');
        $config->addResourceDescriptor(new ResourceDescriptor('/app', 'res'));
        $config->addTagDescriptor(new TagDescriptor('/app/config*.yml', 'config'));
        $config->setOverriddenPackages('acme/blog');
        $config->setPackageOrder(array('acme/blog-extension1', 'acme/blog-extension2'));

        $this->writer->writePackageConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/full-root.json', $this->tempFile);
    }

    public function testWriteRootConfigWithoutRootParameters()
    {
        $config = new RootPackageConfig();
        $config->setPackageName('my/application');
        $config->addResourceDescriptor(new ResourceDescriptor('/app', 'res'));
        $config->addTagDescriptor(new TagDescriptor('/app/config*.yml', 'config'));
        $config->setOverriddenPackages('acme/blog');

        $this->writer->writePackageConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/full.json', $this->tempFile);
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

    public function testWriteConfigDispatchesEvent()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PackageEvents::PACKAGE_JSON_GENERATED, function (JsonEvent $event) {
            $data = $event->getJsonData();

            \PHPUnit_Framework_Assert::assertInternalType('object', $data);
            \PHPUnit_Framework_Assert::assertObjectHasAttribute('name', $data);
            \PHPUnit_Framework_Assert::assertSame('my/application', $data->name);

            $data->name = 'modified';

            $event->setJsonData($data);
        });

        $config = new PackageConfig();
        $config->setPackageName('my/application');

        $this->writer = new PackageJsonWriter($dispatcher);

        $this->writer->writePackageConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/name-modified.json', $this->tempFile);
    }

    public function testWriteConfigDispatchesEventAfterSchemaValidation()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PackageEvents::PACKAGE_JSON_GENERATED, function (JsonEvent $event) {
            $data = $event->getJsonData();

            \PHPUnit_Framework_Assert::assertInternalType('object', $data);
            \PHPUnit_Framework_Assert::assertObjectHasAttribute('name', $data);
            \PHPUnit_Framework_Assert::assertSame('my/application', $data->name);

            // Data is invalid without name, however schema validation already
            // took place so this is allowed. The config reader dispatches an
            // event *before* schema validation, so the data can be "corrected"
            // there
            unset($data->name);

            $event->setJsonData($data);
        });

        $config = new PackageConfig();
        $config->setPackageName('my/application');

        $this->writer = new PackageJsonWriter($dispatcher);

        $this->writer->writePackageConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/empty-object.json', $this->tempFile);
    }
}
