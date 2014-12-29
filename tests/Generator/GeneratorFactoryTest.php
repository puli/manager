<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Generator;

use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Generator\GeneratorFactory;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GeneratorFactoryTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var GeneratorFactory
     */
    private $factory;

    protected function setUp()
    {
        $this->factory = new GeneratorFactory();
    }

    public function provideDiscoveryGeneratorNames()
    {
        return array(
            array('key-value-store', 'Puli\RepositoryManager\Generator\Discovery\KeyValueStoreDiscoveryGenerator'),
            array(__NAMESPACE__.'\Fixtures\TestDiscoveryGenerator', __NAMESPACE__.'\Fixtures\TestDiscoveryGenerator'),
        );
    }

    /**
     * @dataProvider provideDiscoveryGeneratorNames
     */
    public function testCreateDiscoveryGenerator($name, $fqcn)
    {
        $this->assertInstanceOf($fqcn, $this->factory->createDiscoveryGenerator($name));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateDiscoveryGeneratorFailsIfNameNotFound()
    {
        $this->factory->createDiscoveryGenerator('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateDiscoveryGeneratorFailsIfNotFactoryCodeGenerator()
    {
        $this->factory->createDiscoveryGenerator('stdClass');
    }

    public function provideRepositoryGeneratorNames()
    {
        return array(
            array('filesystem', 'Puli\RepositoryManager\Generator\Repository\FilesystemRepositoryGenerator'),
            array(__NAMESPACE__.'\Fixtures\TestRepositoryGenerator', __NAMESPACE__.'\Fixtures\TestRepositoryGenerator'),
        );
    }

    /**
     * @dataProvider provideRepositoryGeneratorNames
     */
    public function testCreateRepositoryGenerator($name, $fqcn)
    {
        $this->assertInstanceOf($fqcn, $this->factory->createRepositoryGenerator($name));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateRepositoryGeneratorFailsIfNameNotFound()
    {
        $this->factory->createRepositoryGenerator('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateRepositoryGeneratorFailsIfNotFactoryCodeGenerator()
    {
        $this->factory->createRepositoryGenerator('stdClass');
    }

    public function provideKeyValueStoreGeneratorNames()
    {
        return array(
            array('flintstone', 'Puli\RepositoryManager\Generator\KeyValueStore\FlintstoneStoreGenerator'),
            array('null', 'Puli\RepositoryManager\Generator\KeyValueStore\NullStoreGenerator'),
            array('array', 'Puli\RepositoryManager\Generator\KeyValueStore\ArrayStoreGenerator'),
            array('memcache', 'Puli\RepositoryManager\Generator\KeyValueStore\MemcacheStoreGenerator'),
            array('memcached', 'Puli\RepositoryManager\Generator\KeyValueStore\MemcachedStoreGenerator'),
            array(__NAMESPACE__.'\Fixtures\TestKeyValueStoreGenerator', __NAMESPACE__.'\Fixtures\TestKeyValueStoreGenerator'),
        );
    }

    /**
     * @dataProvider provideKeyValueStoreGeneratorNames
     */
    public function testCreateKeyValueStoreGenerator($name, $fqcn)
    {
        $this->assertInstanceOf($fqcn, $this->factory->createKeyValueStoreGenerator($name));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateKeyValueStoreGeneratorFailsIfNameNotFound()
    {
        $this->factory->createKeyValueStoreGenerator('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateKeyValueStoreGeneratorFailsIfNotFactoryCodeGenerator()
    {
        $this->factory->createKeyValueStoreGenerator('stdClass');
    }
}
