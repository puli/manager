<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Module;

use PHPUnit_Framework_TestCase;
use Puli\Discovery\Api\Type\BindingParameter;
use Puli\Discovery\Api\Type\BindingType;
use Puli\Discovery\Binding\ClassBinding;
use Puli\Discovery\Binding\ResourceBinding;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Api\Puli;
use Puli\Manager\Api\PuliPlugin;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Module\ModuleFileConverter;
use Puli\Manager\Module\RootModuleFileConverter;
use Puli\Manager\Tests\Discovery\Fixtures\Bar;
use Puli\Manager\Tests\Discovery\Fixtures\Foo;
use Rhumsaa\Uuid\Uuid;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootModuleFileConverterTest extends PHPUnit_Framework_TestCase
{
    const BINDING_UUID1 = '2438256b-c2f5-4a06-a18f-f79755e027dd';

    const BINDING_UUID2 = 'ff7bbf5a-44b1-4bdb-8397-e1c601ad7a2e';

    const BINDING_UUID3 = '93fdf1a4-45b3-4a4e-80b5-77dc1137f5ae';

    /**
     * @var Config
     */
    private $baseConfig;

    /**
     * @var ModuleFileConverter
     */
    private $converter;

    protected function setUp()
    {
        $this->baseConfig = new Config();
        $this->converter = new RootModuleFileConverter();
    }

    public function testToJson()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::OPTIONAL, 1234),
        ));

        $resourceBinding = new ResourceBinding('/app/config*.yml', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID1));
        $classBinding = new ClassBinding(__CLASS__, Bar::clazz, array(), Uuid::fromString(self::BINDING_UUID2));

        $installInfo1 = new InstallInfo('vendor/module1', '/path/to/module1');
        $installInfo1->setInstallerName('composer');
        $installInfo1->addDisabledBindingUuid(Uuid::fromString(self::BINDING_UUID3));
        $installInfo2 = new InstallInfo('vendor/module2', '/path/to/module2');
        $installInfo2->setEnvironment(Environment::DEV);

        $baseConfig = new Config();
        $moduleFile = new RootModuleFile(null, null, $baseConfig);
        $moduleFile->setModuleName('my/application');
        $moduleFile->addPathMapping(new PathMapping('/app', 'res'));
        $moduleFile->addBindingDescriptor(new BindingDescriptor($resourceBinding));
        $moduleFile->addBindingDescriptor(new BindingDescriptor($classBinding));
        $moduleFile->addTypeDescriptor(new BindingTypeDescriptor($type, 'Description of my type.', array(
            'param' => 'Description of the parameter.',
        )));
        $moduleFile->setOverriddenModules(array('acme/blog'));
        $moduleFile->setOverrideOrder(array(
            'acme/blog-extension1',
            'acme/blog-extension2',
        ));
        $moduleFile->addPluginClass('Puli\Manager\Tests\Api\Module\Fixtures\TestPlugin');
        $moduleFile->getConfig()->merge(array(
            Config::PULI_DIR => 'puli-dir',
            Config::FACTORY_OUT_CLASS => 'Puli\MyFactory',
            Config::FACTORY_OUT_FILE => '{$puli-dir}/MyFactory.php',
            Config::REPOSITORY_TYPE => 'my-type',
            Config::REPOSITORY_PATH => '{$puli-dir}/my-repo',
            Config::DISCOVERY_STORE_TYPE => 'my-store-type',
        ));
        $moduleFile->addInstallInfo($installInfo1);
        $moduleFile->addInstallInfo($installInfo2);
        $moduleFile->setExtraKeys(array(
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
                'Puli\Manager\Tests\Api\Module\Fixtures\TestPlugin',
            ),
            'modules' => (object) array(
                'vendor/module1' => (object) array(
                    'install-path' => '/path/to/module1',
                    'installer' => 'composer',
                    'disabled-bindings' => array(
                        self::BINDING_UUID3,
                    ),
                ),
                'vendor/module2' => (object) array(
                    'install-path' => '/path/to/module2',
                    'env' => 'dev',
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonSortsModulesByName()
    {
        $installInfo1 = new InstallInfo('vendor/c', '/path/to/module1');
        $installInfo2 = new InstallInfo('vendor/a', '/path/to/module2');
        $installInfo3 = new InstallInfo('vendor/b', '/path/to/module3');

        $moduleFile = new RootModuleFile();
        $moduleFile->addInstallInfo($installInfo1);
        $moduleFile->addInstallInfo($installInfo2);
        $moduleFile->addInstallInfo($installInfo3);

        $jsonData = (object) array(
            'modules' => (object) array(
                'vendor/a' => (object) array(
                    'install-path' => '/path/to/module2',
                ),
                'vendor/b' => (object) array(
                    'install-path' => '/path/to/module3',
                ),
                'vendor/c' => (object) array(
                    'install-path' => '/path/to/module1',
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonSortsPlugins()
    {
        $moduleFile = new RootModuleFile();
        $moduleFile->addPluginClass(__NAMESPACE__.'\PluginC');
        $moduleFile->addPluginClass(__NAMESPACE__.'\PluginA');
        $moduleFile->addPluginClass(__NAMESPACE__.'\PluginB');

        $jsonData = (object) array(
            'plugins' => array(
                __NAMESPACE__.'\PluginA',
                __NAMESPACE__.'\PluginB',
                __NAMESPACE__.'\PluginC',
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonSortsModuleBindings()
    {
        $installInfo = new InstallInfo('vendor/module1', '/path/to/module1');
        $installInfo->addDisabledBindingUuid(Uuid::fromString('6d02ee67-d845-4789-a9c1-8301351c6f5a'));
        $installInfo->addDisabledBindingUuid(Uuid::fromString('4d02ee67-d845-4789-a9c1-8301351c6f5a'));
        $installInfo->addDisabledBindingUuid(Uuid::fromString('5d02ee67-d845-4789-a9c1-8301351c6f5a'));

        $moduleFile = new RootModuleFile();
        $moduleFile->addInstallInfo($installInfo);

        $jsonData = (object) array(
            'modules' => (object) array(
                'vendor/module1' => (object) array(
                    'install-path' => '/path/to/module1',
                    'disabled-bindings' => array(
                        '4d02ee67-d845-4789-a9c1-8301351c6f5a',
                        '5d02ee67-d845-4789-a9c1-8301351c6f5a',
                        '6d02ee67-d845-4789-a9c1-8301351c6f5a',
                    ),
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonMinimal()
    {
        $baseConfig = new Config();
        $moduleFile = new RootModuleFile(null, null, $baseConfig);

        $this->assertEquals((object) array(), $this->converter->toJson($moduleFile));
    }

    public function testToJsonDoesNotWriteBaseConfigValues()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'puli-dir');
        $moduleFile = new RootModuleFile(null, null, $baseConfig);

        $this->assertEquals((object) array(), $this->converter->toJson($moduleFile));
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
                'Puli\Manager\Tests\Api\Module\Fixtures\TestPlugin',
            ),
            'modules' => (object) array(
                'vendor/module1' => (object) array(
                    'install-path' => '/path/to/module1',
                    'installer' => 'composer',
                    'disabled-bindings' => array(
                        self::BINDING_UUID3,
                    ),
                ),
                'vendor/module2' => (object) array(
                    'install-path' => '/path/to/module2',
                    'env' => 'dev',
                ),
            ),
        );

        $moduleFile = $this->converter->fromJson($jsonData, array(
            'path' => '/path',
            'baseConfig' => $this->baseConfig,
        ));

        $installInfo1 = new InstallInfo('vendor/module1', '/path/to/module1');
        $installInfo1->setInstallerName('composer');
        $installInfo1->addDisabledBindingUuid(Uuid::fromString(self::BINDING_UUID3));
        $installInfo2 = new InstallInfo('vendor/module2', '/path/to/module2');
        $installInfo2->setEnvironment(Environment::DEV);

        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::OPTIONAL, 1234),
        ));

        $resourceBinding = new ResourceBinding('/app/config*.yml', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID1));
        $classBinding = new ClassBinding(__CLASS__, Bar::clazz, array(), Uuid::fromString(self::BINDING_UUID2));

        $this->assertInstanceOf('Puli\Manager\Api\Module\RootModuleFile', $moduleFile);

        $config = $moduleFile->getConfig();

        $this->assertSame('/path', $moduleFile->getPath());
        $this->assertSame('my/application', $moduleFile->getModuleName());
        $this->assertEquals(array('/app' => new PathMapping('/app', array('res'))), $moduleFile->getPathMappings());
        $this->assertEquals(array(new BindingDescriptor($resourceBinding), new BindingDescriptor($classBinding)), $moduleFile->getBindingDescriptors());
        $this->assertEquals(array(new BindingTypeDescriptor($type, 'Description of my type.', array(
            'param' => 'Description of the parameter.',
        ))), $moduleFile->getTypeDescriptors());
        $this->assertSame(array('acme/blog'), $moduleFile->getOverriddenModules());
        $this->assertEquals(array(
            'extra1' => 'value',
            'extra2' => (object) array('key' => 'value'),
        ), $moduleFile->getExtraKeys());
        $this->assertSame(array('acme/blog-extension1', 'acme/blog-extension2'), $moduleFile->getOverrideOrder());
        $this->assertEquals(array($installInfo1, $installInfo2), $moduleFile->getInstallInfos());
        $this->assertSame('puli-dir', $config->get(Config::PULI_DIR));
        $this->assertSame('Puli\MyFactory', $config->get(Config::FACTORY_OUT_CLASS));
        $this->assertSame('puli-dir/MyFactory.php', $config->get(Config::FACTORY_OUT_FILE));
        $this->assertSame('my-type', $config->get(Config::REPOSITORY_TYPE));
        $this->assertSame('puli-dir/my-repo', $config->get(Config::REPOSITORY_PATH));
        $this->assertSame('my-store-type', $config->get(Config::DISCOVERY_STORE_TYPE));
    }

    public function testFromJsonWithEmptyPath()
    {
        $moduleFile = $this->converter->fromJson((object) array(), array(
            'baseConfig' => $this->baseConfig,
        ));

        $this->assertNull($moduleFile->getPath());
    }

    public function testFromJsonMinimal()
    {
        $moduleFile = $this->converter->fromJson((object) array(), array(
            'path' => '/path',
            'baseConfig' => $this->baseConfig,
        ));

        $this->assertInstanceOf('Puli\Manager\Api\Module\RootModuleFile', $moduleFile);
        $this->assertSame('/path', $moduleFile->getPath());
        $this->assertNull($moduleFile->getModuleName());
        $this->assertSame(array(), $moduleFile->getPathMappings());
        $this->assertSame(array(), $moduleFile->getBindingDescriptors());
        $this->assertSame(array(), $moduleFile->getOverriddenModules());
        $this->assertSame(array(), $moduleFile->getOverrideOrder());
    }

    public function testFromJsonInheritsBaseConfig()
    {
        $moduleFile = $this->converter->fromJson((object) array(), array(
            'baseConfig' => $this->baseConfig,
        ));

        $this->baseConfig->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir', $moduleFile->getConfig()->get(Config::PULI_DIR));
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
