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
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Puli;
use Puli\Manager\Api\PuliPlugin;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Package\PackageJsonSerializer;
use Puli\Manager\Tests\JsonTestCase;
use Rhumsaa\Uuid\Uuid;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageJsonSerializerTest extends JsonTestCase
{
    const BINDING_UUID1 = '2438256b-c2f5-4a06-a18f-f79755e027dd';

    const BINDING_UUID2 = 'ff7bbf5a-44b1-4bdb-8397-e1c601ad7a2e';

    const BINDING_UUID3 = '93fdf1a4-45b3-4a4e-80b5-77dc1137f5ae';

    const BINDING_UUID4 = 'd939ea88-01a0-4c7b-8d1e-e0dfcffd66e5';

    const FULL_JSON = <<<JSON
{
    "version": "1.0",
    "name": "my/application",
    "path-mappings": {
        "/app": "res"
    },
    "bindings": {
        "2438256b-c2f5-4a06-a18f-f79755e027dd": {
            "query": "/app/config*.yml",
            "type": "my/type"
        }
    },
    "binding-types": {
        "my/type": {
            "description": "Description of my type.",
            "parameters": {
                "param": {
                    "description": "Description of the parameter.",
                    "default": 1234
                }
            }
        }
    },
    "override": "acme/blog",
    "extra": {
        "extra1": "value",
        "extra2": {
            "key": "value"
        }
    }
}

JSON;

    const FULL_ROOT_JSON = <<<JSON
{
    "version": "1.0",
    "name": "my/application",
    "path-mappings": {
        "/app": "res"
    },
    "bindings": {
        "2438256b-c2f5-4a06-a18f-f79755e027dd": {
            "query": "/app/config*.yml",
            "type": "my/type"
        }
    },
    "binding-types": {
        "my/type": {
            "description": "Description of my type.",
            "parameters": {
                "param": {
                    "description": "Description of the parameter.",
                    "default": 1234
                }
            }
        }
    },
    "override": "acme/blog",
    "override-order": [
        "acme/blog-extension1",
        "acme/blog-extension2"
    ],
    "config": {
        "puli-dir": "puli-dir",
        "factory": {
            "out": {
                "class": "Puli\\\\MyFactory",
                "file": "{\$puli-dir}/MyFactory.php"
            }
        },
        "repository": {
            "type": "my-type",
            "path": "{\$puli-dir}/my-repo"
        },
        "discovery": {
            "store": {
                "type": "my-store-type"
            }
        }
    },
    "plugins": [
        "Puli\\\\Manager\\\\Tests\\\\Api\\\\Package\\\\Fixtures\\\\TestPlugin"
    ],
    "extra": {
        "extra1": "value",
        "extra2": {
            "key": "value"
        }
    },
    "packages": {
        "vendor/package1": {
            "install-path": "/path/to/package1",
            "installer": "composer",
            "disabled-bindings": [
                "4d02ee67-d845-4789-a9c1-8301351c6f5a"
            ]
        },
        "vendor/package2": {
            "install-path": "/path/to/package2",
            "env": "dev"
        }
    }
}

JSON;

    const MINIMAL_JSON = <<<JSON
{
    "version": "1.0"
}

JSON;

    /**
     * @var Config
     */
    private $baseConfig;

    /**
     * @var PackageJsonSerializer
     */
    private $serializer;

    protected function setUp()
    {
        $this->baseConfig = new Config();
        $this->serializer = new PackageJsonSerializer();
    }

    public function testSerializePackageFile()
    {
        $packageFile = new PackageFile();
        $packageFile->setPackageName('my/application');
        $packageFile->addPathMapping(new PathMapping('/app', 'res'));
        $packageFile->addBindingDescriptor(new BindingDescriptor('/app/config*.yml', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID1)));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', 'Description of my type.', array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 1234, 'Description of the parameter.'),
        )));
        $packageFile->setOverriddenPackages(array('acme/blog'));
        $packageFile->setExtraKeys(array(
            'extra1' => 'value',
            'extra2' => array('key' => 'value'),
        ));

        $this->assertJsonEquals(self::FULL_JSON, $this->serializer->serializePackageFile($packageFile));
    }

    public function testSerializePackageFileWritesDefaultParameterValuesOfBindings()
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

        // But not written by the serializer
        $packageFile->addBindingDescriptor($binding);

        $json = <<<JSON
{
    "version": "1.0",
    "bindings": {
        "2438256b-c2f5-4a06-a18f-f79755e027dd": {
            "query": "/app/config*.yml",
            "type": "my/type"
        }
    }
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializePackageFile($packageFile));
    }

    public function testSerializeTypeWithoutDescription()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $json = <<<JSON
{
    "version": "1.0",
    "binding-types": {
        "my/type": {}
    }
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializePackageFile($packageFile));
    }

    public function testSerializePackageFilePathMappings()
    {
        $packageFile = new PackageFile();
        $packageFile->addPathMapping(new PathMapping('/vendor/c', 'foo'));
        $packageFile->addPathMapping(new PathMapping('/vendor/a', 'foo'));
        $packageFile->addPathMapping(new PathMapping('/vendor/b', 'foo'));

        $json = <<<JSON
{
    "version": "1.0",
    "path-mappings": {
        "/vendor/a": "foo",
        "/vendor/b": "foo",
        "/vendor/c": "foo"
    }
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializePackageFile($packageFile));
    }

    public function testSerializePackageFileSortsTypes()
    {
        $packageFile = new PackageFile();
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('vendor/c'));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('vendor/a'));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('vendor/b'));

        $json2 = <<<JSON
{
    "version": "1.0",
    "binding-types": {
        "vendor/a": {},
        "vendor/b": {},
        "vendor/c": {}
    }
}

JSON;

        $this->assertJsonEquals($json2, $this->serializer->serializePackageFile($packageFile));
    }

    public function testSerializePackageFileSortsTypeParameters()
    {
        $packageFile = new PackageFile();
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('vendor/type',
            null, array(
                new BindingParameterDescriptor('c'),
                new BindingParameterDescriptor('a'),
                new BindingParameterDescriptor('b'),
            )));

        $json = <<<JSON
{
    "version": "1.0",
    "binding-types": {
        "vendor/type": {
            "parameters": {
                "a": {},
                "b": {},
                "c": {}
            }
        }
    }
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializePackageFile($packageFile));
    }

    public function testSerializePackageFileSortsBindings()
    {
        $packageFile = new PackageFile();
        $packageFile->addBindingDescriptor(new BindingDescriptor('/vendor/c', 'vendor/a-type', array(), 'glob', Uuid::fromString(self::BINDING_UUID1)));
        $packageFile->addBindingDescriptor(new BindingDescriptor('/vendor/a', 'vendor/b-type', array(), 'glob', Uuid::fromString(self::BINDING_UUID2)));
        $packageFile->addBindingDescriptor(new BindingDescriptor('/vendor/b', 'vendor/b-type', array(), 'glob', Uuid::fromString(self::BINDING_UUID3)));
        $packageFile->addBindingDescriptor(new BindingDescriptor('/vendor/a', 'vendor/a-type', array(), 'glob', Uuid::fromString(self::BINDING_UUID4)));

        $json = <<<JSON
{
    "version": "1.0",
    "bindings": {
        "d939ea88-01a0-4c7b-8d1e-e0dfcffd66e5": {
            "query": "/vendor/a",
            "type": "vendor/a-type"
        },
        "ff7bbf5a-44b1-4bdb-8397-e1c601ad7a2e": {
            "query": "/vendor/a",
            "type": "vendor/b-type"
        },
        "93fdf1a4-45b3-4a4e-80b5-77dc1137f5ae": {
            "query": "/vendor/b",
            "type": "vendor/b-type"
        },
        "2438256b-c2f5-4a06-a18f-f79755e027dd": {
            "query": "/vendor/c",
            "type": "vendor/a-type"
        }
    }
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializePackageFile($packageFile));
    }

    public function testSerializePackageFileSortsBindingParameters()
    {
        $packageFile = new PackageFile();
        $packageFile->addBindingDescriptor(new BindingDescriptor('/path', 'vendor/type', array(
            'c' => 'foo',
            'a' => 'foo',
            'b' => 'foo',
        ), 'glob', Uuid::fromString(self::BINDING_UUID1)));

        $json = <<<JSON
{
    "version": "1.0",
    "bindings": {
        "2438256b-c2f5-4a06-a18f-f79755e027dd": {
            "query": "/path",
            "type": "vendor/type",
            "parameters": {
                "a": "foo",
                "b": "foo",
                "c": "foo"
            }
        }
    }
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializePackageFile($packageFile));
    }

    public function testSerializeBindingParameters()
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

        $json = <<<JSON
{
    "version": "1.0",
    "bindings": {
        "2438256b-c2f5-4a06-a18f-f79755e027dd": {
            "query": "/app/config*.yml",
            "type": "my/type",
            "parameters": {
                "param": "value"
            }
        }
    }
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializePackageFile($packageFile));
    }

    public function testSerializeBindingWithCustomLanguage()
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

        $json = <<<JSON
{
    "version": "1.0",
    "bindings": {
        "2438256b-c2f5-4a06-a18f-f79755e027dd": {
            "query": "//resource[name=\"config.yml\"]",
            "language": "xpath",
            "type": "my/type"
        }
    }
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializePackageFile($packageFile));
    }

    public function testSerializeTypeParameterWithoutDescriptionNorParameters()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 1234),
        )));

        $json = <<<JSON
{
    "version": "1.0",
    "binding-types": {
        "my/type": {
            "parameters": {
                "param": {
                    "default": 1234
                }
            }
        }
    }
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializePackageFile($packageFile));
    }

    public function testSerializeTypeParameterWithoutDefaultValue()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, null, 'Description of the parameter.'),
        )));

        $json = <<<JSON
{
    "version": "1.0",
    "binding-types": {
        "my/type": {
            "parameters": {
                "param": {
                    "description": "Description of the parameter."
                }
            }
        }
    }
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializePackageFile($packageFile));
    }

    public function testSerializeRequiredTypeParameter()
    {
        $baseConfig = new Config();
        $packageFile = new PackageFile(null, null, $baseConfig);
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::REQUIRED),
        )));

        $json = <<<JSON
{
    "version": "1.0",
    "binding-types": {
        "my/type": {
            "parameters": {
                "param": {
                    "required": true
                }
            }
        }
    }
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializePackageFile($packageFile));
    }

    public function testSerializeRootPackageFile()
    {
        $installInfo1 = new InstallInfo('vendor/package1', '/path/to/package1');
        $installInfo1->setInstallerName('composer');
        $installInfo1->addDisabledBindingUuid(Uuid::fromString('4d02ee67-d845-4789-a9c1-8301351c6f5a'));
        $installInfo2 = new InstallInfo('vendor/package2', '/path/to/package2');
        $installInfo2->setEnvironment(Environment::DEV);

        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, null, $baseConfig);
        $packageFile->setPackageName('my/application');
        $packageFile->addPathMapping(new PathMapping('/app', 'res'));
        $packageFile->addBindingDescriptor(new BindingDescriptor('/app/config*.yml', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID1)));
        $packageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', 'Description of my type.', array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 1234, 'Description of the parameter.'),
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
            'extra2' => array('key' => 'value'),
        ));

        $this->assertJsonEquals(self::FULL_ROOT_JSON, $this->serializer->serializeRootPackageFile($packageFile));
    }

    public function testSerializeRootPackageFileSortsPackagesByName()
    {
        $installInfo1 = new InstallInfo('vendor/c', '/path/to/package1');
        $installInfo2 = new InstallInfo('vendor/a', '/path/to/package2');
        $installInfo3 = new InstallInfo('vendor/b', '/path/to/package3');

        $packageFile = new RootPackageFile();
        $packageFile->addInstallInfo($installInfo1);
        $packageFile->addInstallInfo($installInfo2);
        $packageFile->addInstallInfo($installInfo3);

        $json = <<<JSON
{
    "version": "1.0",
    "packages": {
        "vendor/a": {
            "install-path": "/path/to/package2"
        },
        "vendor/b": {
            "install-path": "/path/to/package3"
        },
        "vendor/c": {
            "install-path": "/path/to/package1"
        }
    }
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializeRootPackageFile($packageFile));
    }

    public function testSerializeRootPackageFileSortsPlugins()
    {
        $packageFile = new RootPackageFile();
        $packageFile->addPluginClass(__NAMESPACE__.'\PluginC');
        $packageFile->addPluginClass(__NAMESPACE__.'\PluginA');
        $packageFile->addPluginClass(__NAMESPACE__.'\PluginB');

        $json = <<<JSON
{
    "version": "1.0",
    "plugins": [
        "Puli\\\\Manager\\\\Tests\\\\Package\\\\PluginA",
        "Puli\\\\Manager\\\\Tests\\\\Package\\\\PluginB",
        "Puli\\\\Manager\\\\Tests\\\\Package\\\\PluginC"
    ]
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializeRootPackageFile($packageFile));
    }

    public function testSerializeRootPackageFileSortsPackageBindings()
    {
        $installInfo = new InstallInfo('vendor/package1', '/path/to/package1');
        $installInfo->addDisabledBindingUuid(Uuid::fromString('6d02ee67-d845-4789-a9c1-8301351c6f5a'));
        $installInfo->addDisabledBindingUuid(Uuid::fromString('4d02ee67-d845-4789-a9c1-8301351c6f5a'));
        $installInfo->addDisabledBindingUuid(Uuid::fromString('5d02ee67-d845-4789-a9c1-8301351c6f5a'));

        $packageFile = new RootPackageFile();
        $packageFile->addInstallInfo($installInfo);

        $json = <<<JSON
{
    "version": "1.0",
    "packages": {
        "vendor/package1": {
            "install-path": "/path/to/package1",
            "disabled-bindings": [
                "4d02ee67-d845-4789-a9c1-8301351c6f5a",
                "5d02ee67-d845-4789-a9c1-8301351c6f5a",
                "6d02ee67-d845-4789-a9c1-8301351c6f5a"
            ]
        }
    }
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializeRootPackageFile($packageFile));
    }

    public function testSerializeMinimalRootPackageFile()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, null, $baseConfig);

        $this->assertJsonEquals(self::MINIMAL_JSON, $this->serializer->serializeRootPackageFile($packageFile));
    }

    public function testSerializeRootPackageFileDoesNotWriteBaseConfigValues()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'puli-dir');
        $packageFile = new RootPackageFile(null, null, $baseConfig);

        $this->assertJsonEquals(self::MINIMAL_JSON, $this->serializer->serializeRootPackageFile($packageFile));
    }

    public function testSerializePathMappingsWithMultipleLocalPaths()
    {
        $packageFile = new PackageFile();
        $packageFile->setPackageName('my/application');
        $packageFile->addPathMapping(new PathMapping('/app',
            array('res', 'assets')));

        $json = <<<JSON
{
    "version": "1.0",
    "name": "my/application",
    "path-mappings": {
        "/app": [
            "res",
            "assets"
        ]
    }
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializePackageFile($packageFile));
    }

    public function testSerializeMultipleOverriddenPackages()
    {
        $packageFile = new PackageFile();
        $packageFile->setPackageName('my/application');
        $packageFile->setOverriddenPackages(array('acme/blog1', 'acme/blog2'));

        $json = <<<JSON
{
    "version": "1.0",
    "name": "my/application",
    "override": [
        "acme/blog1",
        "acme/blog2"
    ]
}

JSON;

        $this->assertJsonEquals($json, $this->serializer->serializePackageFile($packageFile));
    }

    public function testUnserializeFullPackageFile()
    {
        $packageFile = $this->serializer->unserializePackageFile(self::FULL_JSON, '/path');

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageFile', $packageFile);
        $this->assertNotInstanceOf('Puli\Manager\Api\Package\RootPackageFile', $packageFile);
        $this->assertSame('/path', $packageFile->getPath());
        $this->assertFullConfig($packageFile);
    }

    public function testUnserializeFullPackageFileWithEmptyPath()
    {
        $packageFile = $this->serializer->unserializePackageFile(self::FULL_JSON);

        $this->assertNull($packageFile->getPath());
    }

    public function testUnserializeFullRootPackageFile()
    {
        $packageFile = $this->serializer->unserializeRootPackageFile(self::FULL_ROOT_JSON, '/path', $this->baseConfig);

        $installInfo1 = new InstallInfo('vendor/package1', '/path/to/package1');
        $installInfo1->setInstallerName('composer');
        $installInfo1->addDisabledBindingUuid(Uuid::fromString('4d02ee67-d845-4789-a9c1-8301351c6f5a'));
        $installInfo2 = new InstallInfo('vendor/package2', '/path/to/package2');
        $installInfo2->setEnvironment(Environment::DEV);

        $this->assertInstanceOf('Puli\Manager\Api\Package\RootPackageFile', $packageFile);
        $this->assertSame('/path', $packageFile->getPath());
        $this->assertFullConfig($packageFile);
        $this->assertSame(array('acme/blog-extension1', 'acme/blog-extension2'), $packageFile->getOverrideOrder());
        $this->assertEquals(array($installInfo1, $installInfo2), $packageFile->getInstallInfos());

        $config = $packageFile->getConfig();
        $this->assertSame('puli-dir', $config->get(Config::PULI_DIR));
        $this->assertSame('Puli\MyFactory', $config->get(Config::FACTORY_OUT_CLASS));
        $this->assertSame('puli-dir/MyFactory.php', $config->get(Config::FACTORY_OUT_FILE));
        $this->assertSame('my-type', $config->get(Config::REPOSITORY_TYPE));
        $this->assertSame('puli-dir/my-repo', $config->get(Config::REPOSITORY_PATH));
        $this->assertSame('my-store-type', $config->get(Config::DISCOVERY_STORE_TYPE));
    }

    public function testUnserializeFullRootPackageFileWithEmptyPath()
    {
        $packageFile = $this->serializer->unserializeRootPackageFile(self::FULL_ROOT_JSON, null, $this->baseConfig);

        $this->assertNull($packageFile->getPath());
    }

    public function testUnserializeMinimalPackageFile()
    {
        $packageFile = $this->serializer->unserializePackageFile(self::MINIMAL_JSON);

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageFile', $packageFile);
        $this->assertNotInstanceOf('Puli\Manager\Api\Package\RootPackageFile', $packageFile);
        $this->assertMinimalConfig($packageFile);
    }

    public function testUnserializeMinimalRootPackageFile()
    {
        $packageFile = $this->serializer->unserializeRootPackageFile(self::MINIMAL_JSON);

        $this->assertInstanceOf('Puli\Manager\Api\Package\RootPackageFile', $packageFile);
        $this->assertMinimalConfig($packageFile);
        $this->assertSame(array(), $packageFile->getOverrideOrder());
    }

    public function testUnserializeBindingTypeWithRequiredParameter()
    {
        $json = <<<JSON
{
    "version": "1.0",
    "binding-types": {
        "my/type": {
            "parameters": {
                "param": {
                    "required": true
                }
            }
        }
    }
}

JSON;

        $packageFile = $this->serializer->unserializePackageFile($json);

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageFile', $packageFile);
        $this->assertEquals(array(
            new BindingTypeDescriptor('my/type', null, array(
                new BindingParameterDescriptor('param', BindingParameterDescriptor::REQUIRED),
            )),
        ), $packageFile->getTypeDescriptors());
    }

    public function testUnserializeBindingWithParameters()
    {
        $json = <<<JSON
{
    "version": "1.0",
    "bindings": {
        "2438256b-c2f5-4a06-a18f-f79755e027dd": {
            "query": "/app/config*.yml",
            "type": "my/type",
            "parameters": {
                "param": "value"
            }
        }
    }
}

JSON;

        $packageFile = $this->serializer->unserializePackageFile($json);

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageFile', $packageFile);
        $this->assertEquals(array(new BindingDescriptor(
            '/app/config*.yml',
            'my/type',
            array('param' => 'value'),
            'glob',
            Uuid::fromString(self::BINDING_UUID1)
        )), $packageFile->getBindingDescriptors());
    }

    public function testUnserializeBindingWithLanguage()
    {
        $json = <<<JSON
{
    "version": "1.0",
    "bindings": {
        "2438256b-c2f5-4a06-a18f-f79755e027dd": {
            "query": "//resource[name=\"config.yml\"]",
            "language": "xpath",
            "type": "my/type"
        }
    }
}

JSON;

        $packageFile = $this->serializer->unserializePackageFile($json);

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageFile', $packageFile);
        $this->assertEquals(array(new BindingDescriptor(
            '//resource[name="config.yml"]',
            'my/type',
            array(),
            'xpath',
            Uuid::fromString(self::BINDING_UUID1)
        )), $packageFile->getBindingDescriptors());
    }

    public function testRootPackageFileInheritsBaseConfig()
    {
        $packageFile = $this->serializer->unserializeRootPackageFile(self::MINIMAL_JSON, null, $this->baseConfig);

        $this->baseConfig->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir', $packageFile->getConfig()->get(Config::PULI_DIR));
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     * @expectedExceptionMessage /path/to/extra-prop.json
     */
    public function testUnserializePackageFileValidatesSchema()
    {
        $json = <<<JSON
{
    "version": "1.0",
    "name": "my/application",
    "foo": "bar"
}

JSON;

        $this->serializer->unserializePackageFile($json, '/path/to/extra-prop.json');
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     * @expectedExceptionMessage /path/to/extra-prop.json
     */
    public function testUnserializeRootPackageFileValidatesSchema()
    {
        $json = <<<JSON
{
    "version": "1.0",
    "name": "my/application",
    "foo": "bar"
}

JSON;

        $this->serializer->unserializeRootPackageFile($json, '/path/to/extra-prop.json');
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     * @expectedExceptionMessage /path/to/win-1258.json
     */
    public function testUnserializePackageFileFailsIfDecodingNotPossible()
    {
        if (defined('JSON_C_VERSION')) {
            $this->markTestSkipped('This error is not reported when using JSONC.');
        }

        $json = file_get_contents(__DIR__.'/Fixtures/json/win-1258.json');

        $this->serializer->unserializePackageFile($json, '/path/to/win-1258.json');
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     * @expectedExceptionMessage /path/to/win-1258.json
     */
    public function testUnserializeRootPackageFileFailsIfDecodingNotPossible()
    {
        if (defined('JSON_C_VERSION')) {
            $this->markTestSkipped('This error is not reported when using JSONC.');
        }

        $json = file_get_contents(__DIR__.'/Fixtures/json/win-1258.json');

        $this->serializer->unserializeRootPackageFile($json, '/path/to/win-1258.json');
    }

    /**
     * @expectedException \Puli\Manager\Api\Package\UnsupportedVersionException
     * @expectedExceptionMessage lowest readable version
     */
    public function testUnserializePackageFileVersionTooLow()
    {
        $json = <<<JSON
{
    "version": "0.9",
    "some": "removed property"
}

JSON;

        $this->serializer->unserializePackageFile($json);
    }

    /**
     * @expectedException \Puli\Manager\Api\Package\UnsupportedVersionException
     * @expectedExceptionMessage highest readable version
     */
    public function testUnserializePackageFileVersionTooHigh()
    {
        $json = <<<JSON
{
    "version": "1.1",
    "some": "new property"
}

JSON;

        $this->serializer->unserializePackageFile($json);
    }

    /**
     * @expectedException \Puli\Manager\Api\Package\UnsupportedVersionException
     * @expectedExceptionMessage lowest readable version
     */
    public function testUnserializeRootPackageFileVersionTooLow()
    {
        $json = <<<JSON
{
    "version": "0.9",
    "some": "removed property"
}

JSON;

        $this->serializer->unserializeRootPackageFile($json);
    }

    /**
     * @expectedException \Puli\Manager\Api\Package\UnsupportedVersionException
     * @expectedExceptionMessage highest readable version
     */
    public function testUnserializeRootPackageFileVersionTooHigh()
    {
        $json = <<<JSON
{
    "version": "1.1",
    "some": "new property"
}

JSON;

        $this->serializer->unserializeRootPackageFile($json);
    }

    ////////////////////////////////////////////////////////////////////////////
    // Test Schema Validation
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testNameMustBeString()
    {
        $json = <<<JSON
{
    "version": "1.0",
    "name": 12345
}

JSON;

        $this->serializer->unserializePackageFile($json);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testResourcesMustBeObject()
    {
        $json = <<<JSON
{
    "version": "1.0",
    "name": "my/application",
    "path-mappings": ["/app", "/app/css"]
}

JSON;

        $this->serializer->unserializePackageFile($json);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testBindingTypesMustBeObject()
    {
        $json = <<<JSON
{
    "version": "1.0",
    "binding-types": [
        { "type": "my/type" }
    ]
}

JSON;

        $this->serializer->unserializePackageFile($json);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testBindingsMustBeObject()
    {
        $json = <<<JSON
{
    "version": "1.0",
    "name": "my/application",
    "bindings": [
        {
            "query": "/app/config*.yml",
            "type": "my/type"
        }
    ]
}

JSON;

        $this->serializer->unserializePackageFile($json);
    }

    public function testOverrideMayBeArray()
    {
        $json = <<<JSON
{
    "version": "1.0",
    "name": "my/application",
    "override": ["acme/blog-extension1", "acme/blog-extension2"]
}

JSON;

        $packageFile = $this->serializer->unserializePackageFile($json);

        $this->assertSame(array('acme/blog-extension1', 'acme/blog-extension2'), $packageFile->getOverriddenPackages());
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testOverrideMustBeStringOrArray()
    {
        $json = <<<JSON
{
    "version": "1.0",
    "name": "my/application",
    "override": 12345
}

JSON;

        $this->serializer->unserializePackageFile($json);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testOverrideEntriesMustBeStrings()
    {
        $json = <<<JSON
{
    "version": "1.0",
    "name": "my/application",
    "override": [123, 456]
}

JSON;

        $this->serializer->unserializePackageFile($json);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testOverrideOrderMustBeArray()
    {
        $json = <<<JSON
{
    "version": "1.0",
    "name": "my/application",
    "override-order": {
        "acme/blog-extension1": "acme/blog-extension2"
    }
}

JSON;

        $this->serializer->unserializePackageFile($json);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testOverrideOrderEntriesMustBeStrings()
    {
        $json = <<<JSON
{
    "version": "1.0",
    "name": "my/application",
    "override-order": [ 12345 ]
}

JSON;

        $this->serializer->unserializePackageFile($json);
    }

    private function assertFullConfig(PackageFile $packageFile)
    {
        $this->assertSame('my/application', $packageFile->getPackageName());
        $this->assertEquals(array('/app' => new PathMapping('/app', array('res'))), $packageFile->getPathMappings());
        $this->assertEquals(array(new BindingDescriptor('/app/config*.yml', 'my/type', array(), 'glob', Uuid::fromString(self::BINDING_UUID1))), $packageFile->getBindingDescriptors());
        $this->assertEquals(array(new BindingTypeDescriptor('my/type', 'Description of my type.', array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 1234, 'Description of the parameter.'),
        ))), $packageFile->getTypeDescriptors());
        $this->assertSame(array('acme/blog'), $packageFile->getOverriddenPackages());
        $this->assertEquals(array(
            'extra1' => 'value',
            'extra2' => (object) array('key' => 'value'),
        ), $packageFile->getExtraKeys());
    }

    private function assertMinimalConfig(PackageFile $packageFile)
    {
        $this->assertNull($packageFile->getPackageName());
        $this->assertSame(array(), $packageFile->getPathMappings());
        $this->assertSame(array(), $packageFile->getBindingDescriptors());
        $this->assertSame(array(), $packageFile->getOverriddenPackages());
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
