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
use Puli\RepositoryManager\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Discovery\BindingParameterDescriptor;
use Puli\RepositoryManager\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Discovery\Store\BindingTypeStore;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\PackageFile\Writer\PackageJsonWriter;
use Puli\RepositoryManager\Plugin\ManagerPlugin;
use Puli\RepositoryManager\Repository\ResourceMapping;
use Puli\RepositoryManager\Tests\JsonWriterTestCase;
use Rhumsaa\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageJsonWriterTest extends JsonWriterTestCase
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
        $packageFile->addResourceMapping(new ResourceMapping('/app', 'res'));
        $packageFile->addBindingDescriptor(BindingDescriptor::create('/app/config*.yml', 'my/type'));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', 'Description of my type.', array(
            new BindingParameterDescriptor('param', false, 1234, 'Description of the parameter.'),
        )));
        $packageFile->setOverriddenPackages('acme/blog');

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/full.json', $this->tempFile);
    }

    public function testWritePackageFileWritesDefaultParameterValuesOfBindings()
    {
        $packageFile = new PackageFile();
        $typeStore = new BindingTypeStore();
        $package = new Package($packageFile, '/path', new InstallInfo('vendor/package', '/path'));

        // We need to create a type and a binding in state ENABLED
        $bindingType = new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', false, 'default'),
        ));
        $typeStore->add($bindingType, $package);
        $bindingType->refreshState($typeStore);

        $binding = BindingDescriptor::create('/app/config*.yml', 'my/type');
        $binding->refreshState($package, $typeStore);

        // The default value is accessible
        $this->assertSame('default', $binding->getParameterValue('param'));

        // But not written by the writer
        $packageFile->addBindingDescriptor($binding);

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/binding-no-default-params.json', $this->tempFile);
    }

    public function testWriteTypeWithoutDescription()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/type-no-description.json', $this->tempFile);
    }

    public function testWritePackageFileResourceMappings()
    {
        $packageFile = new PackageFile();
        $packageFile->addResourceMapping(new ResourceMapping('/vendor/c', 'foo'));
        $packageFile->addResourceMapping(new ResourceMapping('/vendor/a', 'foo'));
        $packageFile->addResourceMapping(new ResourceMapping('/vendor/b', 'foo'));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/sorted-mappings.json', $this->tempFile);
    }

    public function testWritePackageFileSortsTypes()
    {
        $packageFile = new PackageFile();
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('vendor/c'));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('vendor/a'));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('vendor/b'));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/sorted-types.json', $this->tempFile);
    }

    public function testWritePackageFileSortsTypeParameters()
    {
        $packageFile = new PackageFile();
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('c'),
            new BindingParameterDescriptor('a'),
            new BindingParameterDescriptor('b'),
        )));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/sorted-type-params.json', $this->tempFile);
    }

    public function testWritePackageFileSortsBindings()
    {
        $packageFile = new PackageFile();
        $packageFile->addBindingDescriptor(BindingDescriptor::create('/vendor/c', 'vendor/a-type'));
        $packageFile->addBindingDescriptor(BindingDescriptor::create('/vendor/a', 'vendor/b-type'));
        $packageFile->addBindingDescriptor(BindingDescriptor::create('/vendor/b', 'vendor/b-type'));
        $packageFile->addBindingDescriptor(BindingDescriptor::create('/vendor/a', 'vendor/a-type'));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/sorted-bindings.json', $this->tempFile);
    }

    public function testWritePackageFileSortsBindingParameters()
    {
        $packageFile = new PackageFile();
        $packageFile->addBindingDescriptor(BindingDescriptor::create('/path', 'vendor/type', array(
            'c' => 'foo',
            'a' => 'foo',
            'b' => 'foo',
        )));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/sorted-binding-params.json', $this->tempFile);
    }

    public function testWriteBindingParameters()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addBindingDescriptor(BindingDescriptor::create(
            '/app/config*.yml',
            'my/type',
            array('param' => 'value')
        ));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/binding-params.json', $this->tempFile);
    }

    public function testWriteBindingWithCustomLanguage()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addBindingDescriptor(BindingDescriptor::create(
            '//resource[name="config.yml"]',
            'my/type',
            array(),
            'xpath'
        ));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/binding-language.json', $this->tempFile);
    }

    public function testWriteTypeParameterWithoutDescriptionNorParameters()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', false, 1234),
        )));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/type-param-no-description.json', $this->tempFile);
    }

    public function testWriteTypeParameterWithoutDefaultValue()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', false, null, 'Description of the parameter.'),
        )));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/type-param-no-default.json', $this->tempFile);
    }

    public function testWriteRequiredTypeParameter()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', true),
        )));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/type-param-required.json', $this->tempFile);
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
        $packageFile->addBindingDescriptor(BindingDescriptor::create('/app/config*.yml', 'my/type'));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', 'Description of my type.', array(
            new BindingParameterDescriptor('param', false, 1234, 'Description of the parameter.'),
        )));
        $packageFile->setOverriddenPackages('acme/blog');
        $packageFile->setOverrideOrder(array('acme/blog-extension1', 'acme/blog-extension2'));
        $packageFile->addPluginClass('Puli\RepositoryManager\Tests\Package\PackageFile\Fixtures\TestPlugin');
        $packageFile->getConfig()->merge(array(
            Config::PULI_DIR => 'puli-dir',
            Config::FACTORY_CLASS => 'Puli\MyFactory',
            Config::FACTORY_FILE => '{$puli-dir}/MyFactory.php',
            Config::REPOSITORY_TYPE => 'my-type',
            Config::REPOSITORY_PATH => '{$puli-dir}/my-repo',
            Config::DISCOVERY_STORE_TYPE => 'my-store-type',
        ));
        $packageFile->addInstallInfo($installInfo1);
        $packageFile->addInstallInfo($installInfo2);

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/full-root.json', $this->tempFile);
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

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/sorted-packages.json', $this->tempFile);
    }

    public function testWriteRootPackageFileSortsPlugins()
    {
        $packageFile = new RootPackageFile();
        $packageFile->addPluginClass(__NAMESPACE__.'\PluginC');
        $packageFile->addPluginClass(__NAMESPACE__.'\PluginA');
        $packageFile->addPluginClass(__NAMESPACE__.'\PluginB');

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/sorted-plugins.json', $this->tempFile);
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

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/sorted-package-bindings.json', $this->tempFile);
    }

    public function testWriteMinimalRootPackageFile()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, null, $baseConfig);

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertJsonFileEquals(__DIR__.'/Fixtures/minimal.json', $this->tempFile);
    }

    public function testWriteRootPackageFileDoesNotWriteBaseConfigValues()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'puli-dir');
        $packageFile = new RootPackageFile(null, null, $baseConfig);

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertJsonFileEquals(__DIR__.'/Fixtures/minimal.json', $this->tempFile);
    }

    public function testWriteResourcesWithMultipleLocalPaths()
    {
        $packageFile = new PackageFile();
        $packageFile->setPackageName('my/application');
        $packageFile->addResourceMapping(new ResourceMapping('/app', array('res', 'assets')));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/multi-resources.json', $this->tempFile);
    }

    public function testWriteMultipleOverriddenPackages()
    {
        $packageFile = new PackageFile();
        $packageFile->setPackageName('my/application');
        $packageFile->setOverriddenPackages(array('acme/blog1', 'acme/blog2'));

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/multi-overrides.json', $this->tempFile);
    }

    public function testCreateMissingDirectoriesOnDemand()
    {
        $packageFile = new PackageFile();
        $file = $this->tempDir.'/new/config.json';

        $this->writer->writePackageFile($packageFile, $file);

        $this->assertFileExists($file);
        $this->assertJsonFileEquals(__DIR__.'/Fixtures/minimal.json', $file);
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

class PluginA implements ManagerPlugin
{
    public function activate(ProjectEnvironment $environment) {}
}

class PluginB implements ManagerPlugin
{
    public function activate(ProjectEnvironment $environment) {}
}

class PluginC implements ManagerPlugin
{
    public function activate(ProjectEnvironment $environment) {}
}
