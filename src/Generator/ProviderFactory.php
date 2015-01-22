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
 * Creates build recipe providers.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @see    BuildRecipe, BuildRecipeProvider
 */
class ProviderFactory
{
    /**
     * @var string[]
     */
    private static $discoveryProviderClassNames = array(
        'key-value-store' => 'Puli\RepositoryManager\Generator\Discovery\KeyValueStoreDiscoveryRecipeProvider',
    );

    /**
     * @var string[]
     */
    private static $repositoryProviderClassNames = array(
        'filesystem' => 'Puli\RepositoryManager\Generator\Repository\FilesystemRepositoryRecipeProvider',
    );

    /**
     * @var string[]
     */
    private static $keyValueStoreProviderClassNames = array(
        null => 'Puli\RepositoryManager\Generator\KeyValueStore\NullStoreRecipeProvider',
        'null' => 'Puli\RepositoryManager\Generator\KeyValueStore\NullStoreRecipeProvider',
        'array' => 'Puli\RepositoryManager\Generator\KeyValueStore\ArrayStoreRecipeProvider',
        'json-file' => 'Puli\RepositoryManager\Generator\KeyValueStore\JsonFileStoreRecipeProvider',
    );

    /**
     * Creates the provider for a resource discovery recipe.
     *
     * @param string $name The name of the provider. Pass either the
     *                     abbreviation "key-value-store" or a fully-qualified
     *                     name of a class implementing
     *                     {@link BuildRecipeProvider}.
     *
     * @return BuildRecipeProvider The created recipe provider.
     */
    public function createDiscoveryRecipeProvider($name)
    {
        if (isset(self::$discoveryProviderClassNames[$name])) {
            $name = self::$discoveryProviderClassNames[$name];
        } elseif (!class_exists($name)) {
            throw new InvalidArgumentException(sprintf(
                'Neither the discovery type "%s" nor the class "%s" exists.',
                $name,
                $name
            ));
        }

        if (!is_subclass_of($name,
            'Puli\RepositoryManager\Generator\BuildRecipeProvider')) {
            throw new InvalidArgumentException(sprintf(
                'The class "%s" should implement "BuildRecipeProvider".',
                $name
            ));
        }

        return new $name();
    }

    /**
     * Creates the provider for a resource repository recipe.
     *
     * @param string $name The name of the provider. Pass either the
     *                     abbreviation "file-copy" or a fully-qualified
     *                     name of a class implementing
     *                     {@link BuildRecipeProvider}.
     *
     * @return BuildRecipeProvider The created recipe provider.
     */
    public function createRepositoryRecipeProvider($name)
    {
        if (isset(self::$repositoryProviderClassNames[$name])) {
            $name = self::$repositoryProviderClassNames[$name];
        } elseif (!class_exists($name)) {
            throw new InvalidArgumentException(sprintf(
                'Neither the repository type "%s" nor the class "%s" exists.',
                $name,
                $name
            ));
        }

        if (!is_subclass_of($name,
            'Puli\RepositoryManager\Generator\BuildRecipeProvider')) {
            throw new InvalidArgumentException(sprintf(
                'The class "%s" should implement "BuildRecipeProvider".',
                $name
            ));
        }

        return new $name();
    }

    /**
     * Creates the provider for a key-value store recipe.
     *
     * @param string $name The name of the provider. Pass either the
     *                     abbreviation "flintstone" or a fully-qualified
     *                     name of a class implementing
     *                     {@link BuildRecipeProvider}.
     *
     * @return BuildRecipeProvider The created recipe provider.
     */
    public function createKeyValueStoreRecipeProvider($name)
    {
        if (isset(self::$keyValueStoreProviderClassNames[$name])) {
            $name = self::$keyValueStoreProviderClassNames[$name];
        } elseif (!class_exists($name)) {
            throw new InvalidArgumentException(sprintf(
                'Neither the key-value store type "%s" nor the class "%s" exists.',
                $name,
                $name
            ));
        }

        if (!is_subclass_of($name,
            'Puli\RepositoryManager\Generator\BuildRecipeProvider')) {
            throw new InvalidArgumentException(sprintf(
                'The class "%s" should implement "BuildRecipeProvider".',
                $name
            ));
        }

        return new $name();
    }
}
