<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Factory\Generator;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Factory\Generator\GeneratorRegistry;
use Puli\Manager\Factory\Generator\DefaultGeneratorRegistry;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DefaultGeneratorRegistryTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var DefaultGeneratorRegistry
     */
    private $registry;

    protected function setUp()
    {
        $this->registry = new DefaultGeneratorRegistry();
    }

    public function getGeneratorNames()
    {
        return array(
            array(GeneratorRegistry::REPOSITORY, 'filesystem', 'Puli\Manager\Factory\Generator\Repository\FilesystemRepositoryGenerator'),
            array(GeneratorRegistry::REPOSITORY, 'json', 'Puli\Manager\Factory\Generator\Repository\JsonRepositoryGenerator'),
            array(GeneratorRegistry::REPOSITORY, 'path-mapping', 'Puli\Manager\Factory\Generator\Repository\JsonRepositoryGenerator'),
            array(GeneratorRegistry::DISCOVERY, 'json', 'Puli\Manager\Factory\Generator\Discovery\JsonDiscoveryGenerator'),
            array(GeneratorRegistry::DISCOVERY, 'key-value-store', 'Puli\Manager\Factory\Generator\Discovery\KeyValueStoreDiscoveryGenerator'),
            array(GeneratorRegistry::CHANGE_STREAM, 'json', 'Puli\Manager\Factory\Generator\ChangeStream\JsonChangeStreamGenerator'),
            array(GeneratorRegistry::CHANGE_STREAM, 'key-value-store', 'Puli\Manager\Factory\Generator\ChangeStream\KeyValueStoreChangeStreamGenerator'),
            array(GeneratorRegistry::KEY_VALUE_STORE, 'array', 'Puli\Manager\Factory\Generator\KeyValueStore\ArrayStoreGenerator'),
            array(GeneratorRegistry::KEY_VALUE_STORE, 'json', 'Puli\Manager\Factory\Generator\KeyValueStore\JsonFileStoreGenerator'),
            array(GeneratorRegistry::KEY_VALUE_STORE, 'json-file', 'Puli\Manager\Factory\Generator\KeyValueStore\JsonFileStoreGenerator'),
            array(GeneratorRegistry::KEY_VALUE_STORE, 'null', 'Puli\Manager\Factory\Generator\KeyValueStore\NullStoreGenerator'),
            array(GeneratorRegistry::KEY_VALUE_STORE, null, 'Puli\Manager\Factory\Generator\KeyValueStore\NullStoreGenerator'),
            array(GeneratorRegistry::KEY_VALUE_STORE, 'php-redis', 'Puli\Manager\Factory\Generator\KeyValueStore\PhpRedisStoreGenerator'),
            array(GeneratorRegistry::KEY_VALUE_STORE, 'predis', 'Puli\Manager\Factory\Generator\KeyValueStore\PredisStoreGenerator'),
            array(GeneratorRegistry::KEY_VALUE_STORE, 'riak', 'Puli\Manager\Factory\Generator\KeyValueStore\RiakStoreGenerator'),
        );
    }

    /**
     * @dataProvider getGeneratorNames
     */
    public function testGetServiceGenerator($type, $name, $class)
    {
        $generator = $this->registry->getServiceGenerator($type, $name);

        $this->assertInstanceOf($class, $generator);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetServiceGeneratorWithInvalidType()
    {
        $generator = $this->registry->getServiceGenerator('foo', 'bar');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetServiceGeneratorWithUnknownService()
    {
        $generator = $this->registry->getServiceGenerator(GeneratorRegistry::DISCOVERY, 'foobar');
    }
}
