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
use Puli\RepositoryManager\Generator\ProviderFactory;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ProviderFactoryTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var ProviderFactory
     */
    private $factory;

    protected function setUp()
    {
        $this->factory = new ProviderFactory();
    }

    public function getDiscoveryProviderNames()
    {
        return array(
            array('key-value-store', 'Puli\RepositoryManager\Generator\Discovery\KeyValueStoreDiscoveryRecipeProvider'),
            array(__NAMESPACE__.'\Fixtures\TestDiscoveryRecipeProvider', __NAMESPACE__.'\Fixtures\TestDiscoveryRecipeProvider'),
        );
    }

    /**
     * @dataProvider getDiscoveryProviderNames
     */
    public function testCreateDiscoveryRecipeProvider($name, $fqcn)
    {
        $this->assertInstanceOf($fqcn, $this->factory->createDiscoveryRecipeProvider($name));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateDiscoveryRecipeProviderFailsIfNameNotFound()
    {
        $this->factory->createDiscoveryRecipeProvider('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateDiscoveryRecipeProviderFailsIfNotFactoryCodeGenerator()
    {
        $this->factory->createDiscoveryRecipeProvider('stdClass');
    }

    public function getRepositoryRecipeProviderNames()
    {
        return array(
            array('filesystem', 'Puli\RepositoryManager\Generator\Repository\FilesystemRepositoryRecipeProvider'),
            array(__NAMESPACE__.'\Fixtures\TestRepositoryRecipeProvider', __NAMESPACE__.'\Fixtures\TestRepositoryRecipeProvider'),
        );
    }

    /**
     * @dataProvider getRepositoryRecipeProviderNames
     */
    public function testCreateRepositoryRecipeProvider($name, $fqcn)
    {
        $this->assertInstanceOf($fqcn, $this->factory->createRepositoryRecipeProvider($name));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateRepositoryRecipeProviderFailsIfNameNotFound()
    {
        $this->factory->createRepositoryRecipeProvider('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateRepositoryRecipeProviderFailsIfNotFactoryCodeGenerator()
    {
        $this->factory->createRepositoryRecipeProvider('stdClass');
    }

    public function getKeyValueStoreRecipeProviderNames()
    {
        return array(
            array('json-file', 'Puli\RepositoryManager\Generator\KeyValueStore\JsonFileStoreRecipeProvider'),
            array('null', 'Puli\RepositoryManager\Generator\KeyValueStore\NullStoreRecipeProvider'),
            array(null, 'Puli\RepositoryManager\Generator\KeyValueStore\NullStoreRecipeProvider'),
            array('array', 'Puli\RepositoryManager\Generator\KeyValueStore\ArrayStoreRecipeProvider'),
            array(__NAMESPACE__.'\Fixtures\TestKeyValueStoreRecipeProvider', __NAMESPACE__.'\Fixtures\TestKeyValueStoreRecipeProvider'),
        );
    }

    /**
     * @dataProvider getKeyValueStoreRecipeProviderNames
     */
    public function testCreateKeyValueStoreRecipeProvider($name, $fqcn)
    {
        $this->assertInstanceOf($fqcn, $this->factory->createKeyValueStoreRecipeProvider($name));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateKeyValueStoreRecipeProviderFailsIfNameNotFound()
    {
        $this->factory->createKeyValueStoreRecipeProvider('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateKeyValueStoreRecipeProviderFailsIfNotFactoryCodeGenerator()
    {
        $this->factory->createKeyValueStoreRecipeProvider('stdClass');
    }
}
