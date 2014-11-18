<?php

/*
 * This file is part of the Puli Packages package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Packages\Tests\Package\Config\Reader;

use Puli\Packages\Package\Config\PackageConfig;
use Puli\Packages\Package\Config\Reader\PackageJsonReader;
use Puli\Packages\Package\Config\ResourceDefinition;
use Puli\Packages\Package\Config\TagDefinition;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageJsonReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PackageJsonReader
     */
    private $reader;

    protected function setUp()
    {
        $this->reader = new PackageJsonReader();
    }

    public function testReadFullConfig()
    {
        $config = $this->reader->readPackageConfig(__DIR__.'/Fixtures/full.json');

        $this->assertInstanceOf('Puli\Packages\Package\Config\PackageConfig', $config);
        $this->assertNotInstanceOf('Puli\Packages\Package\Config\RootPackageConfig', $config);
        $this->assertFullConfig($config);
    }

    public function testReadFullRootConfig()
    {
        $config = $this->reader->readRootPackageConfig(__DIR__.'/Fixtures/full.json');

        $this->assertInstanceOf('Puli\Packages\Package\Config\RootPackageConfig', $config);
        $this->assertFullConfig($config);
        $this->assertSame(array('acme/blog-extension1', 'acme/blog-extension2'), $config->getPackageOrder());
    }

    public function testReadMinimalConfig()
    {
        $config = $this->reader->readPackageConfig(__DIR__.'/Fixtures/minimal.json');

        $this->assertInstanceOf('Puli\Packages\Package\Config\PackageConfig', $config);
        $this->assertNotInstanceOf('Puli\Packages\Package\Config\RootPackageConfig', $config);
        $this->assertMinimalConfig($config);
    }

    public function testReadMinimalRootConfig()
    {
        $config = $this->reader->readRootPackageConfig(__DIR__.'/Fixtures/minimal.json');

        $this->assertInstanceOf('Puli\Packages\Package\Config\RootPackageConfig', $config);
        $this->assertMinimalConfig($config);
        $this->assertSame(array(), $config->getPackageOrder());
    }

    /**
     * @expectedException \Puli\Packages\InvalidConfigException
     */
    public function testReadConfigValidatesSchema()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/extra-prop.json');
    }

    /**
     * @expectedException \Puli\Packages\InvalidConfigException
     */
    public function testReadRootConfigValidatesSchema()
    {
        $this->reader->readRootPackageConfig(__DIR__.'/Fixtures/extra-prop.json');
    }

    /**
     * @expectedException \Puli\Packages\FileNotFoundException
     */
    public function testReadConfigFailsIfNotFound()
    {
        $this->reader->readPackageConfig('bogus.json');
    }

    /**
     * @expectedException \Puli\Packages\FileNotFoundException
     */
    public function testReadRootConfigFailsIfNotFound()
    {
        $this->reader->readRootPackageConfig('bogus.json');
    }

    ////////////////////////////////////////////////////////////////////////////
    // Test Schema Validation
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Puli\Packages\InvalidConfigException
     */
    public function testNameMustBeString()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/name-not-string.json');
    }

    /**
     * @expectedException \Puli\Packages\InvalidConfigException
     */
    public function testNameIsRequired()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/name-missing.json');
    }

    /**
     * @expectedException \Puli\Packages\InvalidConfigException
     */
    public function testResourcesMustBeObject()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/resources-no-object.json');
    }

    /**
     * @expectedException \Puli\Packages\InvalidConfigException
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
     * @expectedException \Puli\Packages\InvalidConfigException
     */
    public function testResourceEntryNestedEntriesMustBeStrings()
    {
        $this->markTestSkipped('Not supported by the schema validator.');
        return;

//         $this->reader->readPackageConfig(__DIR__.'/Fixtures/resources-entry-entry-no-string.json');
    }

    /**
     * @expectedException \Puli\Packages\InvalidConfigException
     */
    public function testTagsMustBeObject()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/tags-no-object.json');
    }

    /**
     * @expectedException \Puli\Packages\InvalidConfigException
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
     * @expectedException \Puli\Packages\InvalidConfigException
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
     * @expectedException \Puli\Packages\InvalidConfigException
     */
    public function testOverrideMustBeStringOrArray()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/override-no-string.json');
    }

    /**
     * @expectedException \Puli\Packages\InvalidConfigException
     */
    public function testOverrideEntriesMustBeStrings()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/override-entry-no-string.json');
    }

    /**
     * @expectedException \Puli\Packages\InvalidConfigException
     */
    public function testPackageOrderMustBeArray()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/package-order-no-array.json');
    }

    /**
     * @expectedException \Puli\Packages\InvalidConfigException
     */
    public function testPackageOrderEntriesMustBeStrings()
    {
        $this->reader->readPackageConfig(__DIR__.'/Fixtures/package-order-entry-no-string.json');
    }

    private function assertFullConfig(PackageConfig $config)
    {
        $this->assertSame('my/application', $config->getPackageName());
        $this->assertEquals(array(new ResourceDefinition('/app', array('res'))), $config->getResourceDefinitions());
        $this->assertEquals(array(new TagDefinition('/app/config*.yml', array('config'))), $config->getTagDefinitions());
        $this->assertSame(array('acme/blog'), $config->getOverriddenPackages());
    }

    private function assertMinimalConfig(PackageConfig $config)
    {
        $this->assertSame('my/application', $config->getPackageName());
        $this->assertSame(array(), $config->getResourceDefinitions());
        $this->assertSame(array(), $config->getTagDefinitions());
        $this->assertSame(array(), $config->getOverriddenPackages());
    }
}
