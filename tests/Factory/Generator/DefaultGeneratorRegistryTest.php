<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Factory\Generator;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Api\Factory\Generator\GeneratorRegistry;
use Puli\RepositoryManager\Factory\Generator\DefaultGeneratorRegistry;

/**
 * @since  1.0
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
            array(GeneratorRegistry::REPOSITORY, 'filesystem', 'Puli\RepositoryManager\Factory\Generator\Repository\FilesystemRepositoryGenerator'),
            array(GeneratorRegistry::DISCOVERY, 'key-value-store', 'Puli\RepositoryManager\Factory\Generator\Discovery\KeyValueStoreDiscoveryGenerator'),
            array(GeneratorRegistry::KEY_VALUE_STORE, 'array', 'Puli\RepositoryManager\Factory\Generator\KeyValueStore\ArrayStoreGenerator'),
            array(GeneratorRegistry::KEY_VALUE_STORE, 'json-file', 'Puli\RepositoryManager\Factory\Generator\KeyValueStore\JsonFileStoreGenerator'),
            array(GeneratorRegistry::KEY_VALUE_STORE, 'null', 'Puli\RepositoryManager\Factory\Generator\KeyValueStore\NullStoreGenerator'),
            array(GeneratorRegistry::KEY_VALUE_STORE, null, 'Puli\RepositoryManager\Factory\Generator\KeyValueStore\NullStoreGenerator'),
            array(GeneratorRegistry::KEY_VALUE_STORE, 'php-redis', 'Puli\RepositoryManager\Factory\Generator\KeyValueStore\PhpRedisStoreGenerator'),
            array(GeneratorRegistry::KEY_VALUE_STORE, 'predis', 'Puli\RepositoryManager\Factory\Generator\KeyValueStore\PredisStoreGenerator'),
            array(GeneratorRegistry::KEY_VALUE_STORE, 'riak', 'Puli\RepositoryManager\Factory\Generator\KeyValueStore\RiakStoreGenerator'),
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
}
