<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Generator;

use InvalidArgumentException;

/**
 * Creates factory code generators.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GeneratorFactory
{
    /**
     * @var string[]
     */
    private static $discoveryGeneratorClassNames = array(
        'key-value-store' => 'Puli\RepositoryManager\Generator\Discovery\KeyValueStoreDiscoveryGenerator',
    );

    /**
     * @var string[]
     */
    private static $repositoryGeneratorClassNames = array(
        'file-copy' => 'Puli\RepositoryManager\Generator\Repository\FileCopyRepositoryGenerator',
    );

    /**
     * @var string[]
     */
    private static $keyValueStoreGeneratorClassNames = array(
        'null' => 'Puli\RepositoryManager\Generator\KeyValueStore\NullStoreGenerator',
        'array' => 'Puli\RepositoryManager\Generator\KeyValueStore\ArrayStoreGenerator',
        'flintstone' => 'Puli\RepositoryManager\Generator\KeyValueStore\FlintstoneStoreGenerator',
        'memcache' => 'Puli\RepositoryManager\Generator\KeyValueStore\MemcacheStoreGenerator',
        'memcached' => 'Puli\RepositoryManager\Generator\KeyValueStore\MemcachedStoreGenerator',
    );

    /**
     * Creates the generator for the factory code of a resource discovery.
     *
     * @param string $name The name of the generator. Pass either the
     *                     abbreviation "key-value-store" or a fully-qualified
     *                     name of a class implementing
     *                     {@link FactoryCodeGenerator}.
     *
     * @return FactoryCodeGenerator The created factory code generator.
     */
    public function createDiscoveryGenerator($name)
    {
        if (isset(self::$discoveryGeneratorClassNames[$name])) {
            $name = self::$discoveryGeneratorClassNames[$name];
        } elseif (!class_exists($name)) {
            throw new InvalidArgumentException(sprintf(
                'Neither the discovery type "%s" nor the class "%s" exists.',
                $name,
                $name
            ));
        }

        if (!is_subclass_of($name, 'Puli\RepositoryManager\Generator\FactoryCodeGenerator')) {
            throw new InvalidArgumentException(sprintf(
                'The class "%s" should implement "FactoryCodeGenerator".',
                $name
            ));
        }

        return new $name();
    }

    /**
     * Creates the generator for the factory code of a resource repository.
     *
     * @param string $name The name of the generator. Pass either the
     *                     abbreviation "file-copy" or a fully-qualified
     *                     name of a class implementing
     *                     {@link FactoryCodeGenerator}.
     *
     * @return FactoryCodeGenerator The created factory code generator.
     */
    public function createRepositoryGenerator($name)
    {
        if (isset(self::$repositoryGeneratorClassNames[$name])) {
            $name = self::$repositoryGeneratorClassNames[$name];
        } elseif (!class_exists($name)) {
            throw new InvalidArgumentException(sprintf(
                'Neither the repository type "%s" nor the class "%s" exists.',
                $name,
                $name
            ));
        }

        if (!is_subclass_of($name, 'Puli\RepositoryManager\Generator\FactoryCodeGenerator')) {
            throw new InvalidArgumentException(sprintf(
                'The class "%s" should implement "FactoryCodeGenerator".',
                $name
            ));
        }

        return new $name();
    }

    /**
     * Creates the generator for the factory code of a key-value store.
     *
     * @param string $name The name of the generator. Pass either the
     *                     abbreviation "flintstone" or a fully-qualified
     *                     name of a class implementing
     *                     {@link FactoryCodeGenerator}.
     *
     * @return FactoryCodeGenerator The created factory code generator.
     */
    public function createKeyValueStoreGenerator($name)
    {
        if (isset(self::$keyValueStoreGeneratorClassNames[$name])) {
            $name = self::$keyValueStoreGeneratorClassNames[$name];
        } elseif (!class_exists($name)) {
            throw new InvalidArgumentException(sprintf(
                'Neither the key-value store type "%s" nor the class "%s" exists.',
                $name,
                $name
            ));
        }

        if (!is_subclass_of($name, 'Puli\RepositoryManager\Generator\FactoryCodeGenerator')) {
            throw new InvalidArgumentException(sprintf(
                'The class "%s" should implement "FactoryCodeGenerator".',
                $name
            ));
        }

        return new $name();
    }
}
