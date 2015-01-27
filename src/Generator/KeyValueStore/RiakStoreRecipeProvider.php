<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Generator\KeyValueStore;

use InvalidArgumentException;
use Puli\RepositoryManager\Assert\Assert;
use Puli\RepositoryManager\Generator\BuildRecipe;
use Puli\RepositoryManager\Generator\BuildRecipeProvider;
use Puli\RepositoryManager\Generator\ProviderFactory;

/**
 * Creates the build recipe for a {@link RiakStore}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RiakStoreRecipeProvider implements BuildRecipeProvider
{
    private static $defaultOptions = array(
        'host' => '127.0.0.1',
        'port' => 8098,
    );

    /**
     * {@inheritdoc}
     */
    public function getRecipe($varName, $outputDir, $rootDir, array $options, ProviderFactory $providerFactory)
    {
        $options = array_replace(self::$defaultOptions, $options);

        if (!isset($options['bucket'])) {
            throw new InvalidArgumentException('The "bucket" option is missing.');
        }

        Assert::string($options['bucket'], 'The bucket must be a string. Got: %s');
        Assert::string($options['host'], 'The host must be a string. Got: %s');
        Assert::integer($options['port'], 'The port must be an integer. Got: %s');

        $escBucket = var_export($options['bucket'], true);
        $escHost = var_export($options['host'], true);
        $escPort = var_export($options['port'], true);

        $recipe = new BuildRecipe();
        $recipe->addImport('Basho\Riak\Riak');
        $recipe->addImport('Webmozart\KeyValueStore\RiakStore');
        $recipe->addVarDeclaration('$client', "\$client = new Riak($escHost, $escPort);");
        $recipe->addVarDeclaration($varName, "$varName = new RiakStore($escBucket, \$client);");

        return $recipe;
    }
}
