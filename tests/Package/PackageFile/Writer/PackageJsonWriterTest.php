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
use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\PackageFile\Writer\PackageJsonWriter;
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

    public function testWritePackageFileDoesWriteDefaultParameterValuesOfBindings()
    {
        $packageFile = new PackageFile();
        $typeStore = new BindingTypeStore();
        $package = new Package($packageFile, '/path', new InstallInfo('package', '/path'));

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
        $installInfo1 = new InstallInfo('package1', '/path/to/package1');
        $installInfo1->setInstaller('Composer');
        $installInfo1->addEnabledBindingUuid(Uuid::fromString('a54e5668-2b36-43f4-a32c-2d175092b77d'));
        $installInfo1->addDisabledBindingUuid(Uuid::fromString('4d02ee67-d845-4789-a9c1-8301351c6f5a'));
        $installInfo2 = new InstallInfo('package2', '/path/to/package2');

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
            Config::REPOSITORY_STORE_TYPE => 'my-store-type',
        ));
        $packageFile->addInstallInfo($installInfo1);
        $packageFile->addInstallInfo($installInfo2);

        $this->writer->writePackageFile($packageFile, $this->tempFile);

        $this->assertFileExists($this->tempFile);

        $this->assertJsonFileEquals(__DIR__.'/Fixtures/full-root.json', $this->tempFile);
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
