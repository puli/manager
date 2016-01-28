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

use PHPUnit_Framework_TestCase;
use Puli\Discovery\Api\Type\BindingParameter;
use Puli\Discovery\Api\Type\BindingType;
use Puli\Discovery\Binding\ClassBinding;
use Puli\Discovery\Binding\ResourceBinding;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Puli;
use Puli\Manager\Api\PuliPlugin;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Package\PackageFileConverter;
use Puli\Manager\Package\RootPackageFileConverter;
use Puli\Manager\Tests\Discovery\Fixtures\Bar;
use Puli\Manager\Tests\Discovery\Fixtures\Foo;
use Rhumsaa\Uuid\Uuid;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageFileConverterTest extends PHPUnit_Framework_TestCase
{
    const BINDING_UUID1 = '2438256b-c2f5-4a06-a18f-f79755e027dd';

    const BINDING_UUID2 = 'ff7bbf5a-44b1-4bdb-8397-e1c601ad7a2e';

    const BINDING_UUID3 = '93fdf1a4-45b3-4a4e-80b5-77dc1137f5ae';

    /**
     * @var Config
     */
    private $baseConfig;

    /**
     * @var PackageFileConverter
     */
    private $converter;

    protected function setUp()
    {
        $this->baseConfig = new Config();
        $this->converter = new RootPackageFileConverter();
    }

    public function testToJson()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::OPTIONAL, 1234),
        ));

        $resourceBinding = new ResourceBinding('/app/config*.yml', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID1));
        $classBinding = new ClassBinding(__CLASS__, Bar::clazz, array(), Uuid::fromString(self::BINDING_UUID2));

        $installInfo1 = new InstallInfo('vendor/package1', '/path/to/package1');
        $installInfo1->setInstallerName('composer');
        $installInfo1->addDisabledBindingUuid(Uuid::fromString(self::BINDING_UUID3));
        $installInfo2 = new InstallInfo('vendor/package2', '/path/to/package2');
        $installInfo2->setEnvironment(Environment::DEV);

        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, null, $baseConfig);
        $packageFile->setPackageName('my/application');
        $packageFile->addPathMapping(new PathMapping('/app', 'res'));
        $packageFile->addBindingDescriptor(new BindingDescriptor($resourceBinding));
        $packageFile->addBindingDescriptor(new BindingDescriptor($classBinding));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor($type, 'Description of my type.', array(
            'param' => 'Description of the parameter.',
        )));
        $packageFile->setOverriddenPackages(array('acme/blog'));
        $packageFile->setOverrideOrder(array(
            'acme/blog-extension1',
            'acme/blog-extension2',
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
            'extra2' => (object) array('key' => 'value'),
        ));

        $jsonData = (object) array(
            'name' => 'my/application',
            'path-mappings' => (object) array(
                '/app' => 'res',
            ),
            'bindings' => (object) array(
                self::BINDING_UUID1 => (object) array(
                    '_class' => 'Puli\Discovery\Binding\ResourceBinding',
                    'query' => '/app/config*.yml',
                    'type' => Foo::clazz,
                ),
                self::BINDING_UUID2 => (object) array(
                    '_class' => 'Puli\Discovery\Binding\ClassBinding',
                    'class' => __CLASS__,
                    'type' => Bar::clazz,
                ),
            ),
            'binding-types' => (object) array(
                Foo::clazz => (object) array(
                    'description' => 'Description of my type.',
                    'parameters' => (object) array(
                        'param' => (object) array(
                            'description' => 'Description of the parameter.',
                            'default' => 1234,
                        ),
                    ),
                ),
            ),
            'override' => 'acme/blog',
            'extra' => (object) array(
                'extra1' => 'value',
                'extra2' => (object) array(
                    'key' => 'value',
                ),
            ),
            'override-order' => array(
                'acme/blog-extension1',
                'acme/blog-extension2',
            ),
            'config' => (object) array(
                'puli-dir' => 'puli-dir',
                'factory' => array(
                    'out' => array(
                        'class' => 'Puli\MyFactory',
                        'file' => '{$puli-dir}/MyFactory.php',
                    ),
                ),
                'repository' => array(
                    'type' => 'my-type',
                    'path' => '{$puli-dir}/my-repo',
                ),
                'discovery' => array(
                    'store' => array(
                        'type' => 'my-store-type',
                    ),
                ),
            ),
            'plugins' => array(
                'Puli\Manager\Tests\Api\Package\Fixtures\TestPlugin'
            ),
            'packages' => (object) array(
                'vendor/package1' => (object) array(
                    'install-path' => '/path/to/package1',
                    'installer' => 'composer',
                    'disabled-bindings' => array(
                        self::BINDING_UUID3,
                    ),
                ),
                'vendor/package2' => (object) array(
                    'install-path' => '/path/to/package2',
                    'env' => 'dev',
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonSortsPackagesByName()
    {
        $installInfo1 = new InstallInfo('vendor/c', '/path/to/package1');
        $installInfo2 = new InstallInfo('vendor/a', '/path/to/package2');
        $installInfo3 = new InstallInfo('vendor/b', '/path/to/package3');

        $packageFile = new RootPackageFile();
        $packageFile->addInstallInfo($installInfo1);
        $packageFile->addInstallInfo($installInfo2);
        $packageFile->addInstallInfo($installInfo3);

        $jsonData = (object) array(
            'packages' => (object) array(
                'vendor/a' => (object) array(
                    'install-path' => '/path/to/package2',
                ),
                'vendor/b' => (object) array(
                    'install-path' => '/path/to/package3',
                ),
                'vendor/c' => (object) array(
                    'install-path' => '/path/to/package1',
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonSortsPlugins()
    {
        $packageFile = new RootPackageFile();
        $packageFile->addPluginClass(__NAMESPACE__.'\PluginC');
        $packageFile->addPluginClass(__NAMESPACE__.'\PluginA');
        $packageFile->addPluginClass(__NAMESPACE__.'\PluginB');

        $jsonData = (object) array(
            'plugins' => array(
                __NAMESPACE__.'\PluginA',
                __NAMESPACE__.'\PluginB',
                __NAMESPACE__.'\PluginC',
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonSortsPackageBindings()
    {
        $installInfo = new InstallInfo('vendor/package1', '/path/to/package1');
        $installInfo->addDisabledBindingUuid(Uuid::fromString('6d02ee67-d845-4789-a9c1-8301351c6f5a'));
        $installInfo->addDisabledBindingUuid(Uuid::fromString('4d02ee67-d845-4789-a9c1-8301351c6f5a'));
        $installInfo->addDisabledBindingUuid(Uuid::fromString('5d02ee67-d845-4789-a9c1-8301351c6f5a'));

        $packageFile = new RootPackageFile();
        $packageFile->addInstallInfo($installInfo);

        $jsonData = (object) array(
            'packages' => (object) array(
                'vendor/package1' => (object) array(
                    'install-path' => '/path/to/package1',
                    'disabled-bindings' => array(
                        '4d02ee67-d845-4789-a9c1-8301351c6f5a',
                        '5d02ee67-d845-4789-a9c1-8301351c6f5a',
                        '6d02ee67-d845-4789-a9c1-8301351c6f5a',
                    ),
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonMinimal()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, null, $baseConfig);

        $this->assertEquals((object) array(), $this->converter->toJson($packageFile));
    }

    public function testToJsonDoesNotWriteBaseConfigValues()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'puli-dir');
        $packageFile = new RootPackageFile(null, null, $baseConfig);

        $this->assertEquals((object) array(), $this->converter->toJson($packageFile));
    }

    public function testFromJson()
    {
        $jsonData = (object) array(
            'name' => 'my/application',
            'path-mappings' => (object) array(
                '/app' => 'res',
            ),
            'bindings' => (object) array(
                self::BINDING_UUID1 => (object) array(
                    '_class' => 'Puli\Discovery\Binding\ResourceBinding',
                    'query' => '/app/config*.yml',
                    'type' => Foo::clazz,
                ),
                self::BINDING_UUID2 => (object) array(
                    '_class' => 'Puli\Discovery\Binding\ClassBinding',
                    'class' => __CLASS__,
                    'type' => Bar::clazz,
                ),
            ),
            'binding-types' => (object) array(
                Foo::clazz => (object) array(
                    'description' => 'Description of my type.',
                    'parameters' => (object) array(
                        'param' => (object) array(
                            'description' => 'Description of the parameter.',
                            'default' => 1234,
                        ),
                    ),
                ),
            ),
            'override' => 'acme/blog',
            'extra' => (object) array(
                'extra1' => 'value',
                'extra2' => (object) array(
                    'key' => 'value',
                ),
            ),
            'override-order' => array(
                'acme/blog-extension1',
                'acme/blog-extension2',
            ),
            'config' => (object) array(
                'puli-dir' => 'puli-dir',
                'factory' => array(
                    'out' => array(
                        'class' => 'Puli\MyFactory',
                        'file' => '{$puli-dir}/MyFactory.php',
                    ),
                ),
                'repository' => array(
                    'type' => 'my-type',
                    'path' => '{$puli-dir}/my-repo',
                ),
                'discovery' => array(
                    'store' => array(
                        'type' => 'my-store-type',
                    ),
                ),
            ),
            'plugins' => array(
                'Puli\Manager\Tests\Api\Package\Fixtures\TestPlugin'
            ),
            'packages' => (object) array(
                'vendor/package1' => (object) array(
                    'install-path' => '/path/to/package1',
                    'installer' => 'composer',
                    'disabled-bindings' => array(
                        self::BINDING_UUID3,
                    ),
                ),
                'vendor/package2' => (object) array(
                    'install-path' => '/path/to/package2',
                    'env' => 'dev',
                ),
            ),
        );

        $packageFile = $this->converter->fromJson($jsonData, array(
            'path' => '/path',
            'baseConfig' => $this->baseConfig,
        ));

        $installInfo1 = new InstallInfo('vendor/package1', '/path/to/package1');
        $installInfo1->setInstallerName('composer');
        $installInfo1->addDisabledBindingUuid(Uuid::fromString(self::BINDING_UUID3));
        $installInfo2 = new InstallInfo('vendor/package2', '/path/to/package2');
        $installInfo2->setEnvironment(Environment::DEV);

        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::OPTIONAL, 1234),
        ));

        $resourceBinding = new ResourceBinding('/app/config*.yml', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID1));
        $classBinding = new ClassBinding(__CLASS__, Bar::clazz, array(), Uuid::fromString(self::BINDING_UUID2));

        $this->assertInstanceOf('Puli\Manager\Api\Package\RootPackageFile', $packageFile);

        $config = $packageFile->getConfig();

        $this->assertSame('/path', $packageFile->getPath());
        $this->assertSame('my/application', $packageFile->getPackageName());
        $this->assertEquals(array('/app' => new PathMapping('/app', array('res'))), $packageFile->getPathMappings());
        $this->assertEquals(array(new BindingDescriptor($resourceBinding), new BindingDescriptor($classBinding)), $packageFile->getBindingDescriptors());
        $this->assertEquals(array(new BindingTypeDescriptor($type, 'Description of my type.', array(
            'param' => 'Description of the parameter.',
        ))), $packageFile->getTypeDescriptors());
        $this->assertSame(array('acme/blog'), $packageFile->getOverriddenPackages());
        $this->assertEquals(array(
            'extra1' => 'value',
            'extra2' => (object) array('key' => 'value'),
        ), $packageFile->getExtraKeys());
        $this->assertSame(array('acme/blog-extension1', 'acme/blog-extension2'), $packageFile->getOverrideOrder());
        $this->assertEquals(array($installInfo1, $installInfo2), $packageFile->getInstallInfos());
        $this->assertSame('puli-dir', $config->get(Config::PULI_DIR));
        $this->assertSame('Puli\MyFactory', $config->get(Config::FACTORY_OUT_CLASS));
        $this->assertSame('puli-dir/MyFactory.php', $config->get(Config::FACTORY_OUT_FILE));
        $this->assertSame('my-type', $config->get(Config::REPOSITORY_TYPE));
        $this->assertSame('puli-dir/my-repo', $config->get(Config::REPOSITORY_PATH));
        $this->assertSame('my-store-type', $config->get(Config::DISCOVERY_STORE_TYPE));
    }

    public function testFromJsonWithEmptyPath()
    {
        $packageFile = $this->converter->fromJson((object) array(), array(
            'baseConfig' => $this->baseConfig,
        ));

        $this->assertNull($packageFile->getPath());
    }

    public function testFromJsonMinimal()
    {
        $packageFile = $this->converter->fromJson((object) array(), array(
            'path' => '/path',
            'baseConfig' => $this->baseConfig,
        ));

        $this->assertInstanceOf('Puli\Manager\Api\Package\RootPackageFile', $packageFile);
        $this->assertSame('/path', $packageFile->getPath());
        $this->assertNull($packageFile->getPackageName());
        $this->assertSame(array(), $packageFile->getPathMappings());
        $this->assertSame(array(), $packageFile->getBindingDescriptors());
        $this->assertSame(array(), $packageFile->getOverriddenPackages());
        $this->assertSame(array(), $packageFile->getOverrideOrder());
    }

    public function testFromJsonInheritsBaseConfig()
    {
        $packageFile = $this->converter->fromJson((object) array(), array(
            'baseConfig' => $this->baseConfig,
        ));

        $this->baseConfig->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir', $packageFile->getConfig()->get(Config::PULI_DIR));
    }
}

class PluginA implements PuliPlugin
{
    public function activate(Puli $puli)
    {
    }
}

class PluginB implements PuliPlugin
{
    public function activate(Puli $puli)
    {
    }
}

class PluginC implements PuliPlugin
{
    public function activate(Puli $puli)
    {
    }
}
