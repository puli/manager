<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Package\Config\Reader;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Event\JsonEvent;
use Puli\PackageManager\Event\PackageEvents;
use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\Reader\PackageJsonReader;
use Puli\PackageManager\Package\Config\ResourceDescriptor;
use Puli\PackageManager\Package\Config\TagDescriptor;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageJsonReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var GlobalConfig
     */
    private $globalConfig;

    /**
     * @var PackageJsonReader
     */
    private $reader;

    protected function setUp()
    {
        $this->globalConfig = new GlobalConfig();
        $this->reader = new PackageJsonReader($this->globalConfig);
    }

    public function testReadFullConfig()
    {
        $config = $this->reader->readPackageConfig(__DIR__.'/Fixtures/full.json');

        $this->assertInstanceOf('Puli\PackageManager\Package\Config\PackageConfig', $config);
        $this->assertNotInstanceOf('Puli\PackageManager\Package\Config\RootPackageConfig', $config);
        $this->assertSame(__DIR__.'/Fixtures/full.json', $config->getPath());
        $this->assertFullConfig($config);
    }

    public function testReadFullRootConfig()
    {
        $config = $this->reader->readRootPackageConfig(__DIR__.'/Fixtures/full.json');

        $this->assertInstanceOf('Puli\PackageManager\Package\Config\RootPackageConfig', $config);
        $this->assertSame(__DIR__.'/Fixtures/full.json', $config->getPath());
        $this->assertFullConfig($config);
        $this->assertSame(array('acme/blog-extension1', 'acme/blog-extension2'), $config->getPackageOrder());
        $this->assertSame('packages.json', $config->getPackageRepositoryConfig());
        $this->assertSame('resource-repository.php', $config->getGeneratedResourceRepository());
        $this->assertSame('cache', $config->getResourceRepositoryCache());
        $this->assertSame(array('Puli\PackageManager\Tests\Config\Fixtures\TestPlugin'), $config->getPluginClasses());
    }

    public function testReadMinimalConfig()
    {
        $config = $this->reader->readPackageConfig(__DIR__.'/Fixtures/minimal.json');

        $this->assertInstanceOf('Puli\PackageManager\Package\Config\PackageConfig', $config);
        $this->assertNotInstanceOf('Puli\PackageManager\Package\Config\RootPackageConfig', $config);
        $this->assertMinimalConfig($config);
    }

    public function testReadMinimalRootConfig()
    {
        $config = $this->reader->readRootPackageConfig(__DIR__.'/Fixtures/minimal.json');

        $this->assertInstanceOf('Puli\PackageManager\Package\Config\RootPackageConfig', $config);
        $this->assertMinimalConfig($config);
        $this->assertSame(array(), $config->getPackageOrder());
    }

    public function testRootConfigReceivesGlobalConfig()
    {
        $config = $this->reader->readRootPackageConfig(__DIR__.'/Fixtures/minimal.json');

        $this->globalConfig->setPackageRepositoryConfig('modified');

        $this->assertSame('modified', $config->getPackageRepositoryConfig());
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     * @expectedExceptionMessage empty.json
     */
    public function testReadConfigRequiresName()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/empty.json');
    }

    public function testReadRootConfigSetsNameToDefault()
    {
        $config = $this->reader->readRootPackageConfig(__DIR__.'/Fixtures/empty.json');

        $this->assertSame('__root__', $config->getPackageName());
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     * @expectedExceptionMessage extra-prop.json
     */
    public function testReadConfigValidatesSchema()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/extra-prop.json');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     * @expectedExceptionMessage extra-prop.json
     */
    public function testReadRootConfigValidatesSchema()
    {
        $this->reader->readRootPackageConfig(__DIR__.'/Fixtures/extra-prop.json');
    }

    /**
     * @expectedException \Puli\PackageManager\FileNotFoundException
     * @expectedExceptionMessage bogus.json
     */
    public function testReadConfigFailsIfNotFound()
    {
        $this->reader->readPackageConfig('bogus.json');
    }

    /**
     * @expectedException \Puli\PackageManager\FileNotFoundException
     * @expectedExceptionMessage bogus.json
     */
    public function testReadRootConfigFailsIfNotFound()
    {
        $this->reader->readRootPackageConfig('bogus.json');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     * @expectedExceptionMessage win-1258.json
     */
    public function testReadConfigFailsIfDecodingNotPossible()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/win-1258.json');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     * @expectedExceptionMessage win-1258.json
     */
    public function testReadRootConfigFailsIfDecodingNotPossible()
    {
        $this->reader->readRootPackageConfig(__DIR__.'/Fixtures/win-1258.json');
    }

    public function testReadConfigDispatchesEvent()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PackageEvents::PACKAGE_JSON_LOADED, function (JsonEvent $event) {
            \PHPUnit_Framework_Assert::assertSame(__DIR__.'/Fixtures/minimal.json', $event->getJsonPath());

            $data = $event->getJsonData();
            \PHPUnit_Framework_Assert::assertInternalType('object', $data);
            \PHPUnit_Framework_Assert::assertObjectHasAttribute('name', $data);
            \PHPUnit_Framework_Assert::assertSame('my/application', $data->name);

            $data->name = 'modified';

            $event->setJsonData($data);
        });

        $this->reader = new PackageJsonReader($this->globalConfig, $dispatcher);

        $config = $this->reader->readPackageConfig(__DIR__.'/Fixtures/minimal.json');

        $this->assertInstanceOf('Puli\PackageManager\Package\Config\PackageConfig', $config);
        $this->assertSame('modified', $config->getPackageName());
    }

    public function testReadRootConfigDispatchesEvent()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PackageEvents::PACKAGE_JSON_LOADED, function (JsonEvent $event) {
            \PHPUnit_Framework_Assert::assertSame(__DIR__.'/Fixtures/minimal.json', $event->getJsonPath());

            $data = $event->getJsonData();
            \PHPUnit_Framework_Assert::assertInternalType('object', $data);
            \PHPUnit_Framework_Assert::assertObjectHasAttribute('name', $data);
            \PHPUnit_Framework_Assert::assertSame('my/application', $data->name);

            $data->name = 'modified';

            $event->setJsonData($data);
        });

        $this->reader = new PackageJsonReader($this->globalConfig, $dispatcher);

        $config = $this->reader->readRootPackageConfig(__DIR__.'/Fixtures/minimal.json');

        $this->assertInstanceOf('Puli\PackageManager\Package\Config\RootPackageConfig', $config);
        $this->assertSame('modified', $config->getPackageName());
    }

    public function testReadConfigDispatchesEventBeforeValidation()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PackageEvents::PACKAGE_JSON_LOADED, function (JsonEvent $event) {
            \PHPUnit_Framework_Assert::assertSame(__DIR__.'/Fixtures/name-missing.json', $event->getJsonPath());

            $data = $event->getJsonData();
            \PHPUnit_Framework_Assert::assertInternalType('object', $data);
            \PHPUnit_Framework_Assert::assertObjectNotHasAttribute('name', $data);

            // Add name
            $data->name = 'my/application';

            $event->setJsonData($data);
        });

        $this->reader = new PackageJsonReader($this->globalConfig, $dispatcher);

        $config = $this->reader->readPackageConfig(__DIR__.'/Fixtures/name-missing.json');

        $this->assertInstanceOf('Puli\PackageManager\Package\Config\PackageConfig', $config);
        $this->assertSame('my/application', $config->getPackageName());
    }

    ////////////////////////////////////////////////////////////////////////////
    // Test Schema Validation
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testNameMustBeString()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/name-not-string.json');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testNameIsRequired()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/name-missing.json');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testResourcesMustBeObject()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/resources-no-object.json');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testResourceEntriesMustBeStrings()
    {
        $this->markTestSkipped('Not supported by the schema validator.');
        return;

        // $this->reader->readPackageConfig(__DIR__.'/Fixtures/resources-entry-no-string.json');
    }

    public function testResourceEntriesMayBeArrays()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/resources-entry-array.json');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testResourceEntryNestedEntriesMustBeStrings()
    {
        $this->markTestSkipped('Not supported by the schema validator.');
        return;

//         $this->reader->readPackageConfig(__DIR__.'/Fixtures/resources-entry-entry-no-string.json');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testTagsMustBeObject()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/tags-no-object.json');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testTagEntriesMustBeStrings()
    {
        $this->markTestSkipped('Not supported by the schema validator.');
        return;

        // $this->reader->readPackageConfig(__DIR__.'/Fixtures/tags-entry-no-string.json');
    }

    public function testTagEntriesMayBeArrays()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/tags-entry-array.json');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testTagEntryNestedEntriesMustBeStrings()
    {
        $this->markTestSkipped('Not supported by the schema validator.');
        return;

//         $this->reader->readPackageConfig(__DIR__.'/Fixtures/tags-entry-entry-no-string.json');
    }

    public function testOverrideMayBeArray()
    {
        $config = $this->reader->readPackageConfig(__DIR__.'/Fixtures/override-array.json');

        $this->assertSame(array('acme/blog-extension1', 'acme/blog-extension2'), $config->getOverriddenPackages());
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testOverrideMustBeStringOrArray()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/override-no-string.json');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testOverrideEntriesMustBeStrings()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/override-entry-no-string.json');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testPackageOrderMustBeArray()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/package-order-no-array.json');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testPackageOrderEntriesMustBeStrings()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/package-order-entry-no-string.json');
    }

    private function assertFullConfig(PackageConfig $config)
    {
        $this->assertSame('my/application', $config->getPackageName());
        $this->assertEquals(array(new ResourceDescriptor('/app', array('res'))), $config->getResourceDescriptors());
        $this->assertEquals(array(new TagDescriptor('/app/config*.yml', array('config'))), $config->getTagDescriptors());
        $this->assertSame(array('acme/blog'), $config->getOverriddenPackages());
    }

    private function assertMinimalConfig(PackageConfig $config)
    {
        $this->assertSame('my/application', $config->getPackageName());
        $this->assertSame(array(), $config->getResourceDescriptors());
        $this->assertSame(array(), $config->getTagDescriptors());
        $this->assertSame(array(), $config->getOverriddenPackages());
    }
}
