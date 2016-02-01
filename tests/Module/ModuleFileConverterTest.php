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

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\Discovery\Api\Type\BindingParameter;
use Puli\Discovery\Api\Type\BindingType;
use Puli\Discovery\Binding\ClassBinding;
use Puli\Discovery\Binding\ResourceBinding;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Module\ModuleFileConverter;
use Puli\Manager\Tests\Discovery\Fixtures\Bar;
use Puli\Manager\Tests\Discovery\Fixtures\Baz;
use Puli\Manager\Tests\Discovery\Fixtures\Foo;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Json\Versioning\JsonVersioner;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleFileConverterTest extends PHPUnit_Framework_TestCase
{
    const BINDING_UUID1 = '2438256b-c2f5-4a06-a18f-f79755e027dd';

    const BINDING_UUID2 = 'ff7bbf5a-44b1-4bdb-8397-e1c601ad7a2e';

    const BINDING_UUID3 = '93fdf1a4-45b3-4a4e-80b5-77dc1137f5ae';

    const BINDING_UUID4 = 'd939ea88-01a0-4c7b-8d1e-e0dfcffd66e5';

    /**
     * @var Config
     */
    private $baseConfig;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|JsonVersioner
     */
    private $versioner;

    /**
     * @var ModuleFileConverter
     */
    private $converter;

    protected function setUp()
    {
        $this->baseConfig = new Config();
        $this->versioner = $this->getMock('Webmozart\Json\Versioning\JsonVersioner');
        $this->converter = new ModuleFileConverter($this->versioner);
    }

    public function testToJson()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::OPTIONAL, 1234),
        ));

        $resourceBinding = new ResourceBinding('/app/config*.yml', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID1));
        $classBinding = new ClassBinding(__CLASS__, Bar::clazz, array(), Uuid::fromString(self::BINDING_UUID2));

        $moduleFile = new ModuleFile();
        $moduleFile->setModuleName('my/application');
        $moduleFile->addPathMapping(new PathMapping('/app', 'res'));
        $moduleFile->addBindingDescriptor(new BindingDescriptor($resourceBinding));
        $moduleFile->addBindingDescriptor(new BindingDescriptor($classBinding));
        $moduleFile->addTypeDescriptor(new BindingTypeDescriptor($type, 'Description of my type.', array(
            'param' => 'Description of the parameter.',
        )));
        $moduleFile->setDependencies(array('acme/blog'));
        $moduleFile->setExtraKeys(array(
            'extra1' => 'value',
            'extra2' => (object) array('key' => 'value'),
        ));

        $jsonData = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'name' => 'my/application',
            'resources' => (object) array(
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
            'depend' => array('acme/blog'),
            'extra' => (object) array(
                'extra1' => 'value',
                'extra2' => (object) array(
                    'key' => 'value',
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonIgnoresDefaultParameterValuesOfBindings()
    {
        $moduleFile = new ModuleFile();
        $module = new Module($moduleFile, '/path', new InstallInfo('vendor/module', '/path'));

        // We need to create a type and a binding in state ENABLED
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::OPTIONAL, 'default'),
        ));
        $typeDescriptor = new BindingTypeDescriptor($type);
        $typeDescriptor->load($module);

        $binding = new ResourceBinding('/app/config*.yml', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID1));
        $bindingDescriptor = new BindingDescriptor($binding);
        $bindingDescriptor->load($module, $typeDescriptor);

        // The default value is accessible
        $this->assertSame('default', $binding->getParameterValue('param'));

        // But not written by the serializer
        $moduleFile->addBindingDescriptor($bindingDescriptor);

        $jsonData = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'bindings' => (object) array(
                self::BINDING_UUID1 => (object) array(
                    '_class' => 'Puli\Discovery\Binding\ResourceBinding',
                    'query' => '/app/config*.yml',
                    'type' => Foo::clazz,
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonSortsPathMappings()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->addPathMapping(new PathMapping('/vendor/c', 'foo'));
        $moduleFile->addPathMapping(new PathMapping('/vendor/a', 'foo'));
        $moduleFile->addPathMapping(new PathMapping('/vendor/b', 'foo'));

        $jsonData = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'resources' => (object) array(
                '/vendor/a' => 'foo',
                '/vendor/b' => 'foo',
                '/vendor/c' => 'foo',
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonSortsTypes()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Baz::clazz)));
        $moduleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $moduleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Bar::clazz)));

        $jsonData = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'binding-types' => (object) array(
                Bar::clazz => (object) array(),
                Baz::clazz => (object) array(),
                Foo::clazz => (object) array(),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonSortsTypeParameters()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('c'),
            new BindingParameter('a'),
            new BindingParameter('b'),
        ));

        $moduleFile = new ModuleFile();
        $moduleFile->addTypeDescriptor(new BindingTypeDescriptor($type));

        $jsonData = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'binding-types' => (object) array(
                Foo::clazz => (object) array(
                    'parameters' => (object) array(
                        'a' => (object) array(),
                        'b' => (object) array(),
                        'c' => (object) array(),
                    ),
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonSortsBindings()
    {
        $binding1 = new ResourceBinding('/vendor/c', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID1));
        $binding2 = new ResourceBinding('/vendor/a', Bar::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID2));
        $binding3 = new ResourceBinding('/vendor/b', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID3));
        $binding4 = new ClassBinding(__CLASS__, Bar::clazz, array(), Uuid::fromString(self::BINDING_UUID4));

        $moduleFile = new ModuleFile();
        $moduleFile->addBindingDescriptor(new BindingDescriptor($binding1));
        $moduleFile->addBindingDescriptor(new BindingDescriptor($binding2));
        $moduleFile->addBindingDescriptor(new BindingDescriptor($binding3));
        $moduleFile->addBindingDescriptor(new BindingDescriptor($binding4));

        // sort by UUID
        $jsonData = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'bindings' => (object) array(
                self::BINDING_UUID1 => (object) array(
                    '_class' => 'Puli\Discovery\Binding\ResourceBinding',
                    'query' => '/vendor/c',
                    'type' => Foo::clazz,
                ),
                self::BINDING_UUID3 => (object) array(
                    '_class' => 'Puli\Discovery\Binding\ResourceBinding',
                    'query' => '/vendor/b',
                    'type' => Foo::clazz,
                ),
                self::BINDING_UUID4 => (object) array(
                    '_class' => 'Puli\Discovery\Binding\ClassBinding',
                    'class' => __CLASS__,
                    'type' => Bar::clazz,
                ),
                self::BINDING_UUID2 => (object) array(
                    '_class' => 'Puli\Discovery\Binding\ResourceBinding',
                    'query' => '/vendor/a',
                    'type' => Bar::clazz,
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonSortsBindingParameters()
    {
        $binding = new ResourceBinding('/path', Foo::clazz, array(
            'c' => 'foo',
            'a' => 'foo',
            'b' => 'foo',
        ), 'glob', Uuid::fromString(self::BINDING_UUID1));

        $moduleFile = new ModuleFile();
        $moduleFile->addBindingDescriptor(new BindingDescriptor($binding));

        $jsonData = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'bindings' => (object) array(
                self::BINDING_UUID1 => (object) array(
                    '_class' => 'Puli\Discovery\Binding\ResourceBinding',
                    'query' => '/path',
                    'type' => Foo::clazz,
                    'parameters' => (object) array(
                        'a' => 'foo',
                        'b' => 'foo',
                        'c' => 'foo',
                    ),
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonBindingWithCustomLanguage()
    {
        $binding = new ResourceBinding('//resource[name="config.yml"]', Foo::clazz, array(), 'xpath', Uuid::fromString(self::BINDING_UUID1));

        $moduleFile = new ModuleFile();
        $moduleFile->addBindingDescriptor(new BindingDescriptor($binding));

        $jsonData = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'bindings' => (object) array(
                self::BINDING_UUID1 => (object) array(
                    '_class' => 'Puli\Discovery\Binding\ResourceBinding',
                    'query' => '//resource[name="config.yml"]',
                    'language' => 'xpath',
                    'type' => Foo::clazz,
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonTypeWithoutDescription()
    {
        $baseConfig = new Config();
        $moduleFile = new ModuleFile(null, null, $baseConfig);
        $moduleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));

        $jsonData = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'binding-types' => (object) array(
                Foo::clazz => (object) array(),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonTypeParameterWithoutDescriptionNorParameters()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::OPTIONAL, 1234),
        ));

        $moduleFile = new ModuleFile();
        $moduleFile->addTypeDescriptor(new BindingTypeDescriptor($type));

        $jsonData = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'binding-types' => (object) array(
                Foo::clazz => (object) array(
                    'parameters' => (object) array(
                        'param' => (object) array(
                            'default' => 1234,
                        ),
                    ),
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonTypeParameterWithoutDefaultValue()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::OPTIONAL),
        ));

        $moduleFile = new ModuleFile();
        $moduleFile->addTypeDescriptor(new BindingTypeDescriptor($type, null, array(
            'param' => 'Description of the parameter.',
        )));

        $jsonData = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'binding-types' => (object) array(
                Foo::clazz => (object) array(
                    'parameters' => (object) array(
                        'param' => (object) array(
                            'description' => 'Description of the parameter.',
                        ),
                    ),
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonRequiredTypeParameter()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::REQUIRED),
        ));

        $moduleFile = new ModuleFile();
        $moduleFile->addTypeDescriptor(new BindingTypeDescriptor($type));

        $jsonData = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'binding-types' => (object) array(
                Foo::clazz => (object) array(
                    'parameters' => (object) array(
                        'param' => (object) array(
                            'required' => true,
                        ),
                    ),
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonPathMappingWithMultipleLocalPaths()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->addPathMapping(new PathMapping('/app', array('res', 'assets')));

        $jsonData = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'resources' => (object) array(
                '/app' => array('res', 'assets'),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testToJsonMultipleOverriddenModules()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->setDependencies(array('acme/blog1', 'acme/blog2'));

        $jsonData = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'depend' => array(
                'acme/blog1',
                'acme/blog2',
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($moduleFile));
    }

    public function testFromJson()
    {
        $jsonData = (object) array(
            'name' => 'my/application',
            'resources' => (object) array(
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
            'depend' => array('acme/blog'),
            'extra' => (object) array(
                'extra1' => 'value',
                'extra2' => (object) array(
                    'key' => 'value',
                ),
            ),
        );

        $this->versioner->expects($this->once())
            ->method('parseVersion')
            ->with($jsonData)
            ->willReturn('2.0');

        $moduleFile = $this->converter->fromJson($jsonData, array(
            'path' => '/path',
        ));

        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::OPTIONAL, 1234),
        ));

        $resourceBinding = new ResourceBinding('/app/config*.yml', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID1));
        $classBinding = new ClassBinding(__CLASS__, Bar::clazz, array(), Uuid::fromString(self::BINDING_UUID2));

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleFile', $moduleFile);
        $this->assertNotInstanceOf('Puli\Manager\Api\Module\RootModuleFile', $moduleFile);
        $this->assertSame('/path', $moduleFile->getPath());
        $this->assertSame('2.0', $moduleFile->getVersion());
        $this->assertSame('my/application', $moduleFile->getModuleName());
        $this->assertEquals(array('/app' => new PathMapping('/app', array('res'))), $moduleFile->getPathMappings());
        $this->assertEquals(array(new BindingDescriptor($resourceBinding), new BindingDescriptor($classBinding)), $moduleFile->getBindingDescriptors());
        $this->assertEquals(array(new BindingTypeDescriptor($type, 'Description of my type.', array(
            'param' => 'Description of the parameter.',
        ))), $moduleFile->getTypeDescriptors());
        $this->assertSame(array('acme/blog'), $moduleFile->getDependencies());
        $this->assertEquals(array(
            'extra1' => 'value',
            'extra2' => (object) array('key' => 'value'),
        ), $moduleFile->getExtraKeys());
        $this->assertSame('/path', $moduleFile->getPath());
    }

    public function testFromJsonEmptyPath()
    {
        $this->versioner->expects($this->once())
            ->method('parseVersion')
            ->willReturn('2.0');

        $moduleFile = $this->converter->fromJson((object) array());

        $this->assertNull($moduleFile->getPath());
    }

    public function testFromJsonMinimal()
    {
        $this->versioner->expects($this->once())
            ->method('parseVersion')
            ->willReturn('2.0');

        $moduleFile = $this->converter->fromJson((object) array(), array(
            'path' => '/path',
        ));

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleFile', $moduleFile);
        $this->assertNotInstanceOf('Puli\Manager\Api\Module\RootModuleFile', $moduleFile);
        $this->assertSame('/path', $moduleFile->getPath());
        $this->assertSame('2.0', $moduleFile->getVersion());
        $this->assertNull($moduleFile->getModuleName());
        $this->assertSame(array(), $moduleFile->getPathMappings());
        $this->assertSame(array(), $moduleFile->getBindingDescriptors());
        $this->assertSame(array(), $moduleFile->getDependencies());
    }

    public function testFromJsonBindingTypeWithRequiredParameter()
    {
        $jsonData = (object) array(
            'binding-types' => (object) array(
                Foo::clazz => (object) array(
                    'parameters' => (object) array(
                        'param' => (object) array(
                            'required' => true,
                        ),
                    ),
                ),
            ),
        );

        $this->versioner->expects($this->once())
            ->method('parseVersion')
            ->with($jsonData)
            ->willReturn('2.0');

        $moduleFile = $this->converter->fromJson($jsonData);

        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::REQUIRED),
        ));

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleFile', $moduleFile);
        $this->assertEquals(array(new BindingTypeDescriptor($type)), $moduleFile->getTypeDescriptors());
    }

    public function testFromJsonBindingWithParameters()
    {
        $jsonData = (object) array(
            'bindings' => (object) array(
                self::BINDING_UUID1 => (object) array(
                    'query' => '/path',
                    'type' => Foo::clazz,
                    'parameters' => (object) array(
                        'param' => 'value',
                    ),
                ),
            ),
        );

        $this->versioner->expects($this->once())
            ->method('parseVersion')
            ->with($jsonData)
            ->willReturn('2.0');

        $moduleFile = $this->converter->fromJson($jsonData);

        $binding = new ResourceBinding('/path', Foo::clazz, array('param' => 'value'), 'glob', Uuid::fromString(self::BINDING_UUID1));

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleFile', $moduleFile);
        $this->assertEquals(array(new BindingDescriptor($binding)), $moduleFile->getBindingDescriptors());
    }

    public function testFromJsonBindingWithLanguage()
    {
        $jsonData = (object) array(
            'bindings' => (object) array(
                self::BINDING_UUID1 => (object) array(
                    'query' => '//resource[name="config.yml"]',
                    'language' => 'xpath',
                    'type' => Foo::clazz,
                ),
            ),
        );

        $this->versioner->expects($this->once())
            ->method('parseVersion')
            ->with($jsonData)
            ->willReturn('2.0');

        $moduleFile = $this->converter->fromJson($jsonData);

        $binding = new ResourceBinding('//resource[name="config.yml"]', Foo::clazz, array(), 'xpath', Uuid::fromString(self::BINDING_UUID1));

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleFile', $moduleFile);
        $this->assertEquals(array(new BindingDescriptor($binding)), $moduleFile->getBindingDescriptors());
    }

    public function testFromJsonBindingWithExplicitClass()
    {
        $jsonData = (object) array(
            'bindings' => (object) array(
                self::BINDING_UUID1 => (object) array(
                    '_class' => 'Puli\Discovery\Binding\ResourceBinding',
                    'query' => '/path',
                    'type' => Foo::clazz,
                ),
            ),
        );

        $this->versioner->expects($this->once())
            ->method('parseVersion')
            ->with($jsonData)
            ->willReturn('2.0');

        $moduleFile = $this->converter->fromJson($jsonData);

        $binding = new ResourceBinding('/path', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID1));

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleFile', $moduleFile);
        $this->assertEquals(array(new BindingDescriptor($binding)), $moduleFile->getBindingDescriptors());
    }

    public function testFromJsonClassBinding()
    {
        $jsonData = (object) array(
            'bindings' => (object) array(
                self::BINDING_UUID1 => (object) array(
                    '_class' => 'Puli\Discovery\Binding\ClassBinding',
                    'class' => __CLASS__,
                    'type' => Foo::clazz,
                ),
            ),
        );

        $this->versioner->expects($this->once())
            ->method('parseVersion')
            ->with($jsonData)
            ->willReturn('2.0');

        $moduleFile = $this->converter->fromJson($jsonData);

        $binding = new ClassBinding(__CLASS__, Foo::clazz, array(), Uuid::fromString(self::BINDING_UUID1));

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleFile', $moduleFile);
        $this->assertEquals(array(new BindingDescriptor($binding)), $moduleFile->getBindingDescriptors());
    }
}
