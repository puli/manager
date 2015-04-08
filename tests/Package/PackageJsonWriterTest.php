<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Package;

use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingParameterDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Puli;
use Puli\Manager\Api\PuliPlugin;
use Puli\Manager\Api\Repository\ResourceMapping;
use Puli\Manager\Package\PackageJsonWriter;
use Puli\Manager\Tests\JsonWriterTestCase;
use Rhumsaa\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageJsonWriterTest extends JsonWriterTestCase
{
    const BINDING_UUID1 = '2438256b-c2f5-4a06-a18f-f79755e027dd';
    const BINDING_UUID2 = 'ff7bbf5a-44b1-4bdb-8397-e1c601ad7a2e';
    const BINDING_UUID3 = '93fdf1a4-45b3-4a4e-80b5-77dc1137f5ae';
    const BINDING_UUID4 = 'd939ea88-01a0-4c7b-8d1e-e0dfcffd66e5';

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

        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/PackageJsonWriterTest_temp'.rand(10000, 99999), 0777, true)) {
        }
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
        $packageFile->addResourceMapping(new ResourceMapping('/app', 'res'));
        $packageFile->addBindingDescriptor(new BindingDescriptor('/app/config*.yml', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID1)));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', 'Description of my type.', array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 1234, 'Description of the parameter.'),
        )));
        $packageFile->setOverriddenPackages(array('acme/blog'));
        $packageFile->setExtraKeys(array(
            'extra1' => 'value',
            'extra2' => array('key' => 'value'),
        ));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/full.json', $this->tempFile);
    }

    public function testWritePackageFileWritesDefaultParameterValuesOfBindings()
    {
        $packageFile = new PackageFile();
        $package = new Package($packageFile, '/path', new InstallInfo('vendor/package', '/path'));

        // We need to create a type and a binding in state ENABLED
        $bindingType = new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 'default'),
        ));
        $bindingType->load($package);

        $binding = new BindingDescriptor('/app/config*.yml', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID1));
        $binding->load($package, $bindingType);

        // The default value is accessible
        $this->assertSame('default', $binding->getParameterValue('param'));

        // But not written by the writer
        $packageFile->addBindingDescriptor($binding);

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/binding-no-default-params.json', $this->tempFile);
    }

    public function testWriteTypeWithoutDescription()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/type-no-description.json', $this->tempFile);
    }

    public function testWritePackageFileResourceMappings()
    {
        $packageFile = new PackageFile();
        $packageFile->addResourceMapping(new ResourceMapping('/vendor/c', 'foo'));
        $packageFile->addResourceMapping(new ResourceMapping('/vendor/a', 'foo'));
        $packageFile->addResourceMapping(new ResourceMapping('/vendor/b', 'foo'));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/sorted-mappings.json', $this->tempFile);
    }

    public function testWritePackageFileSortsTypes()
    {
        $packageFile = new PackageFile();
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('vendor/c'));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('vendor/a'));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('vendor/b'));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/sorted-types.json', $this->tempFile);
    }

    public function testWritePackageFileSortsTypeParameters()
    {
        $packageFile = new PackageFile();
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('vendor/type',
            null, array(
                new BindingParameterDescriptor('c'),
                new BindingParameterDescriptor('a'),
                new BindingParameterDescriptor('b'),
            )));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/sorted-type-params.json', $this->tempFile);
    }

    public function testWritePackageFileSortsBindings()
    {
        $packageFile = new PackageFile();
        $packageFile->addBindingDescriptor(new BindingDescriptor('/vendor/c', 'vendor/a-type', array(), 'glob', Uuid::fromString(self::BINDING_UUID1)));
        $packageFile->addBindingDescriptor(new BindingDescriptor('/vendor/a', 'vendor/b-type', array(), 'glob', Uuid::fromString(self::BINDING_UUID2)));
        $packageFile->addBindingDescriptor(new BindingDescriptor('/vendor/b', 'vendor/b-type', array(), 'glob', Uuid::fromString(self::BINDING_UUID3)));
        $packageFile->addBindingDescriptor(new BindingDescriptor('/vendor/a', 'vendor/a-type', array(), 'glob', Uuid::fromString(self::BINDING_UUID4)));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/sorted-bindings.json', $this->tempFile);
    }

    public function testWritePackageFileSortsBindingParameters()
    {
        $packageFile = new PackageFile();
        $packageFile->addBindingDescriptor(new BindingDescriptor('/path', 'vendor/type', array(
            'c' => 'foo',
            'a' => 'foo',
            'b' => 'foo',
        ), 'glob', Uuid::fromString(self::BINDING_UUID1)));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/sorted-binding-params.json', $this->tempFile);
    }

    public function testWriteBindingParameters()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addBindingDescriptor(new BindingDescriptor(
            '/app/config*.yml',
            'my/type',
            array('param' => 'value'),
            'glob',
            Uuid::fromString(self::BINDING_UUID1)
        ));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/binding-params.json', $this->tempFile);
    }

    public function testWriteBindingWithCustomLanguage()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addBindingDescriptor(new BindingDescriptor(
            '//resource[name="config.yml"]',
            'my/type',
            array(),
            'xpath',
            Uuid::fromString(self::BINDING_UUID1)
        ));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/binding-language.json', $this->tempFile);
    }

    public function testWriteTypeParameterWithoutDescriptionNorParameters()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 1234),
        )));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/type-param-no-description.json', $this->tempFile);
    }

    public function testWriteTypeParameterWithoutDefaultValue()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, null, 'Description of the parameter.'),
        )));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/type-param-no-default.json', $this->tempFile);
    }

    public function testWriteRequiredTypeParameter()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::REQUIRED),
        )));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/type-param-required.json', $this->tempFile);
    }

    public function testWriteRootPackageFile()
    {
        $installInfo1 = new InstallInfo('vendor/package1', '/path/to/package1');
        $installInfo1->setInstallerName('composer');
        $installInfo1->addEnabledBindingUuid(Uuid::fromString('a54e5668-2b36-43f4-a32c-2d175092b77d'));
        $installInfo1->addDisabledBindingUuid(Uuid::fromString('4d02ee67-d845-4789-a9c1-8301351c6f5a'));
        $installInfo2 = new InstallInfo('vendor/package2', '/path/to/package2');

        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, null, $baseConfig);
        $packageFile->setPackageName('my/application');
        $packageFile->addResourceMapping(new ResourceMapping('/app', 'res'));
        $packageFile->addBindingDescriptor(new BindingDescriptor('/app/config*.yml', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID1)));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', 'Description of my type.', array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 1234, 'Description of the parameter.'),
        )));
        $packageFile->setOverriddenPackages(array('acme/blog'));
        $packageFile->setOverrideOrder(array(
            'acme/blog-extension1',
            'acme/blog-extension2'
        ));
        $packageFile->addPluginClass('Puli\Manager\Tests\Api\Package\Fixtures\TestPlugin');
        $packageFile->getConfig()->merge(array(
            Config::PULI_DIR => 'puli-dir',
            Config::FACTORY_OUT_CLASS => 'Puli\MyFactory',
            Config::FACTORY_OUT_FILE => '{$puli-dir}/MyFactory.php',
            Config::REPOSITORY_TYPE => 'my-type',
            Config::REPOSITORY_PATH => '{$puli-dir}/my-repo',
            Config::DISCOVERY_STORE_TYPE => 'my-store-type',
        ));
        $packageFile->addInstallInfo($installInfo1);
        $packageFile->addInstallInfo($installInfo2);
        $packageFile->setExtraKeys(array(
            'extra1' => 'value',
            'extra2' => array('key' => 'value'),
        ));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/full-root.json', $this->tempFile);
    }

    public function testWriteRootPackageFileSortsPackagesByName()
    {
        $installInfo1 = new InstallInfo('vendor/c', '/path/to/package1');
        $installInfo2 = new InstallInfo('vendor/a', '/path/to/package2');
        $installInfo3 = new InstallInfo('vendor/b', '/path/to/package3');

        $packageFile = new RootPackageFile();
        $packageFile->addInstallInfo($installInfo1);
        $packageFile->addInstallInfo($installInfo2);
        $packageFile->addInstallInfo($installInfo3);

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/sorted-packages.json', $this->tempFile);
    }

    public function testWriteRootPackageFileSortsPlugins()
    {
        $packageFile = new RootPackageFile();
        $packageFile->addPluginClass(__NAMESPACE__.'\PluginC');
        $packageFile->addPluginClass(__NAMESPACE__.'\PluginA');
        $packageFile->addPluginClass(__NAMESPACE__.'\PluginB');

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/sorted-plugins.json', $this->tempFile);
    }

    public function testWriteRootPackageFileSortsPackageBindings()
    {
        $installInfo = new InstallInfo('vendor/package1', '/path/to/package1');
        $installInfo->addEnabledBindingUuid(Uuid::fromString('c54e5668-2b36-43f4-a32c-2d175092b77d'));
        $installInfo->addEnabledBindingUuid(Uuid::fromString('a54e5668-2b36-43f4-a32c-2d175092b77d'));
        $installInfo->addEnabledBindingUuid(Uuid::fromString('b54e5668-2b36-43f4-a32c-2d175092b77d'));
        $installInfo->addDisabledBindingUuid(Uuid::fromString('6d02ee67-d845-4789-a9c1-8301351c6f5a'));
        $installInfo->addDisabledBindingUuid(Uuid::fromString('4d02ee67-d845-4789-a9c1-8301351c6f5a'));
        $installInfo->addDisabledBindingUuid(Uuid::fromString('5d02ee67-d845-4789-a9c1-8301351c6f5a'));

        $packageFile = new RootPackageFile();
        $packageFile->addInstallInfo($installInfo);

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/sorted-package-bindings.json', $this->tempFile);
    }

    public function testWriteMinimalRootPackageFile()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, null, $baseConfig);

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/minimal.json', $this->tempFile);
    }

    public function testWriteRootPackageFileDoesNotWriteBaseConfigValues()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'puli-dir');
        $packageFile = new RootPackageFile(null, null, $baseConfig);

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/minimal.json', $this->tempFile);
    }

    public function testWriteResourcesWithMultipleLocalPaths()
    {
        $packageFile = new PackageFile();
        $packageFile->setPackageName('my/application');
        $packageFile->addResourceMapping(new ResourceMapping('/app',
            array('res', 'assets')));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/multi-resources.json', $this->tempFile);
    }

    public function testWriteMultipleOverriddenPackages()
    {
        $packageFile = new PackageFile();
        $packageFile->setPackageName('my/application');
        $packageFile->setOverriddenPackages(array('acme/blog1', 'acme/blog2'));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/multi-overrides.json', $this->tempFile);
    }

    public function testCreateMissingDirectoriesOnDemand()
    {
        $packageFile = new PackageFile();
        $file = $this->tempDir.'/new/config.json';

        $this->writer->writePackageFile($packageFile, $file);

        $this->assertFileExists($file);
        $this->assertJsonFileEquals(__DIR__.'/Fixtures/json/minimal.json', $file);
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
     * @expectedException \Puli\Manager\Api\IOException
     */
    public function testWriteConfigExpectsValidPath($invalidPath)
    {
        $packageFile = new PackageFile();
        $packageFile->setPackageName('my/application');

        $this->writer->writePackageFile($packageFile, $invalidPath);
    }
}

class PluginA implements PuliPlugin
{
    public function activate(Puli $puli) {}
}

class PluginB implements PuliPlugin
{
    public function activate(Puli $puli) {}
}

class PluginC implements PuliPlugin
{
    public function activate(Puli $puli) {}
}
