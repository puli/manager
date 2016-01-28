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
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Package\PackageFileConverter;
use Puli\Manager\Tests\Discovery\Fixtures\Bar;
use Puli\Manager\Tests\Discovery\Fixtures\Baz;
use Puli\Manager\Tests\Discovery\Fixtures\Foo;
use Rhumsaa\Uuid\Uuid;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileConverterTest extends PHPUnit_Framework_TestCase
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
     * @var PackageFileConverter
     */
    private $converter;

    protected function setUp()
    {
        $this->baseConfig = new Config();
        $this->converter = new PackageFileConverter();
    }

    public function testToJson()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::OPTIONAL, 1234),
        ));

        $resourceBinding = new ResourceBinding('/app/config*.yml', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID1));
        $classBinding = new ClassBinding(__CLASS__, Bar::clazz, array(), Uuid::fromString(self::BINDING_UUID2));

        $packageFile = new PackageFile();
        $packageFile->setPackageName('my/application');
        $packageFile->addPathMapping(new PathMapping('/app', 'res'));
        $packageFile->addBindingDescriptor(new BindingDescriptor($resourceBinding));
        $packageFile->addBindingDescriptor(new BindingDescriptor($classBinding));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor($type, 'Description of my type.', array(
            'param' => 'Description of the parameter.',
        )));
        $packageFile->setOverriddenPackages(array('acme/blog'));
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
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonIgnoresDefaultParameterValuesOfBindings()
    {
        $packageFile = new PackageFile();
        $package = new Package($packageFile, '/path', new InstallInfo('vendor/package', '/path'));

        // We need to create a type and a binding in state ENABLED
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::OPTIONAL, 'default'),
        ));
        $typeDescriptor = new BindingTypeDescriptor($type);
        $typeDescriptor->load($package);

        $binding = new ResourceBinding('/app/config*.yml', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID1));
        $bindingDescriptor = new BindingDescriptor($binding);
        $bindingDescriptor->load($package, $typeDescriptor);

        // The default value is accessible
        $this->assertSame('default', $binding->getParameterValue('param'));

        // But not written by the serializer
        $packageFile->addBindingDescriptor($bindingDescriptor);

        $jsonData = (object) array(
            'bindings' => (object) array(
                self::BINDING_UUID1 => (object) array(
                    '_class' => 'Puli\Discovery\Binding\ResourceBinding',
                    'query' => '/app/config*.yml',
                    'type' => Foo::clazz,
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonSortsPathMappings()
    {
        $packageFile = new PackageFile();
        $packageFile->addPathMapping(new PathMapping('/vendor/c', 'foo'));
        $packageFile->addPathMapping(new PathMapping('/vendor/a', 'foo'));
        $packageFile->addPathMapping(new PathMapping('/vendor/b', 'foo'));

        $jsonData = (object) array(
            'path-mappings' => (object) array(
                '/vendor/a' => 'foo',
                '/vendor/b' => 'foo',
                '/vendor/c' => 'foo',
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonSortsTypes()
    {
        $packageFile = new PackageFile();
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Baz::clazz)));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Bar::clazz)));

        $jsonData = (object) array(
            'binding-types' => (object) array(
                Bar::clazz => (object) array(),
                Baz::clazz => (object) array(),
                Foo::clazz => (object) array(),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonSortsTypeParameters()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('c'),
            new BindingParameter('a'),
            new BindingParameter('b'),
        ));

        $packageFile = new PackageFile();
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor($type));

        $jsonData = (object) array(
            'binding-types' => (object) array(
                Foo::clazz => (object) array(
                    'parameters' => (object) array(
                        'a' => (object) array(),
                        'b' => (object) array(),
                        'c' => (object) array(),
                    )
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonSortsBindings()
    {
        $binding1 = new ResourceBinding('/vendor/c', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID1));
        $binding2 = new ResourceBinding('/vendor/a', Bar::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID2));
        $binding3 = new ResourceBinding('/vendor/b', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID3));
        $binding4 = new ClassBinding(__CLASS__, Bar::clazz, array(), Uuid::fromString(self::BINDING_UUID4));

        $packageFile = new PackageFile();
        $packageFile->addBindingDescriptor(new BindingDescriptor($binding1));
        $packageFile->addBindingDescriptor(new BindingDescriptor($binding2));
        $packageFile->addBindingDescriptor(new BindingDescriptor($binding3));
        $packageFile->addBindingDescriptor(new BindingDescriptor($binding4));

        // sort by UUID
        $jsonData = (object) array(
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

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonSortsBindingParameters()
    {
        $binding = new ResourceBinding('/path', Foo::clazz, array(
            'c' => 'foo',
            'a' => 'foo',
            'b' => 'foo',
        ), 'glob', Uuid::fromString(self::BINDING_UUID1));

        $packageFile = new PackageFile();
        $packageFile->addBindingDescriptor(new BindingDescriptor($binding));

        $jsonData = (object) array(
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

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonBindingWithCustomLanguage()
    {
        $binding = new ResourceBinding('//resource[name="config.yml"]', Foo::clazz, array(), 'xpath', Uuid::fromString(self::BINDING_UUID1));

        $packageFile = new PackageFile();
        $packageFile->addBindingDescriptor(new BindingDescriptor($binding));

        $jsonData = (object) array(
            'bindings' => (object) array(
                self::BINDING_UUID1 => (object) array(
                    '_class' => 'Puli\Discovery\Binding\ResourceBinding',
                    'query' => '//resource[name="config.yml"]',
                    'language' => 'xpath',
                    'type' => Foo::clazz,
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonTypeWithoutDescription()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));

        $jsonData = (object) array(
            'binding-types' => (object) array(
                Foo::clazz => (object) array(),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonTypeParameterWithoutDescriptionNorParameters()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::OPTIONAL, 1234),
        ));

        $packageFile = new PackageFile();
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor($type));

        $jsonData = (object) array(
            'binding-types' => (object) array(
                Foo::clazz => (object) array(
                    'parameters' => (object) array(
                        'param' => (object) array(
                            'default' => 1234,
                        ),
                    )
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonTypeParameterWithoutDefaultValue()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::OPTIONAL),
        ));

        $packageFile = new PackageFile();
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor($type, null, array(
            'param' => 'Description of the parameter.',
        )));

        $jsonData = (object) array(
            'binding-types' => (object) array(
                Foo::clazz => (object) array(
                    'parameters' => (object) array(
                        'param' => (object) array(
                            'description' => 'Description of the parameter.',
                        ),
                    )
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonRequiredTypeParameter()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::REQUIRED),
        ));

        $packageFile = new PackageFile();
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor($type));

        $jsonData = (object) array(
            'binding-types' => (object) array(
                Foo::clazz => (object) array(
                    'parameters' => (object) array(
                        'param' => (object) array(
                            'required' => true,
                        ),
                    )
                ),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonPathMappingWithMultipleLocalPaths()
    {
        $packageFile = new PackageFile();
        $packageFile->addPathMapping(new PathMapping('/app', array('res', 'assets')));

        $jsonData = (object) array(
            'path-mappings' => (object) array(
                '/app' => array('res', 'assets'),
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
    }

    public function testToJsonMultipleOverriddenPackages()
    {
        $packageFile = new PackageFile();
        $packageFile->setOverriddenPackages(array('acme/blog1', 'acme/blog2'));

        $jsonData = (object) array(
            'override' => array(
                'acme/blog1',
                'acme/blog2',
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($packageFile));
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
        );

        $packageFile = $this->converter->fromJson($jsonData, array(
            'path' => '/path',
        ));


        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::OPTIONAL, 1234),
        ));

        $resourceBinding = new ResourceBinding('/app/config*.yml', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID1));
        $classBinding = new ClassBinding(__CLASS__, Bar::clazz, array(), Uuid::fromString(self::BINDING_UUID2));

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageFile', $packageFile);
        $this->assertNotInstanceOf('Puli\Manager\Api\Package\RootPackageFile', $packageFile);
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
        $this->assertSame('/path', $packageFile->getPath());
    }

    public function testFromJsonEmptyPath()
    {
        $packageFile = $this->converter->fromJson((object) array());

        $this->assertNull($packageFile->getPath());
    }

    public function testFromJsonMinimal()
    {
        $packageFile = $this->converter->fromJson((object) array(), array(
            'path' => '/path',
        ));

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageFile', $packageFile);
        $this->assertNotInstanceOf('Puli\Manager\Api\Package\RootPackageFile', $packageFile);
        $this->assertSame('/path', $packageFile->getPath());
        $this->assertNull($packageFile->getPackageName());
        $this->assertSame(array(), $packageFile->getPathMappings());
        $this->assertSame(array(), $packageFile->getBindingDescriptors());
        $this->assertSame(array(), $packageFile->getOverriddenPackages());
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
                    )
                ),
            ),
        );

        $packageFile = $this->converter->fromJson($jsonData);

        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::REQUIRED),
        ));

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageFile', $packageFile);
        $this->assertEquals(array(new BindingTypeDescriptor($type)), $packageFile->getTypeDescriptors());
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

        $packageFile = $this->converter->fromJson($jsonData);

        $binding = new ResourceBinding('/path', Foo::clazz, array('param' => 'value'), 'glob', Uuid::fromString(self::BINDING_UUID1));

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageFile', $packageFile);
        $this->assertEquals(array(new BindingDescriptor($binding)), $packageFile->getBindingDescriptors());
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

        $packageFile = $this->converter->fromJson($jsonData);

        $binding = new ResourceBinding('//resource[name="config.yml"]', Foo::clazz, array(), 'xpath', Uuid::fromString(self::BINDING_UUID1));

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageFile', $packageFile);
        $this->assertEquals(array(new BindingDescriptor($binding)), $packageFile->getBindingDescriptors());
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

        $packageFile = $this->converter->fromJson($jsonData);

        $binding = new ResourceBinding('/path', Foo::clazz, array(), 'glob', Uuid::fromString(self::BINDING_UUID1));

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageFile', $packageFile);
        $this->assertEquals(array(new BindingDescriptor($binding)), $packageFile->getBindingDescriptors());
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

        $packageFile = $this->converter->fromJson($jsonData);

        $binding = new ClassBinding(__CLASS__, Foo::clazz, array(), Uuid::fromString(self::BINDING_UUID1));

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageFile', $packageFile);
        $this->assertEquals(array(new BindingDescriptor($binding)), $packageFile->getBindingDescriptors());
    }
}
