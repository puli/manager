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

use Puli\RepositoryManager\Assert\Assert;
use Puli\RepositoryManager\Generator\BuildRecipe;
use Puli\RepositoryManager\Generator\BuildRecipeProvider;
use Puli\RepositoryManager\Generator\ProviderFactory;

/**
 * Creates the build recipe for a {@link PhpRedisStore}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PhpRedisStoreRecipeProvider implements BuildRecipeProvider
{
    private static $defaultOptions = array(
        'host' => '127.0.0.1',
        'port' => 6379,
    );

    /**
     * {@inheritdoc}
     */
    public function getRecipe($varName, $outputDir, $rootDir, array $options, ProviderFactory $providerFactory)
    {
        $options = array_replace(self::$defaultOptions, $options);

        Assert::string($options['host'], 'The host must be a string. Got: %s');
        Assert::integer($options['port'], 'The port must be an integer. Got: %s');

        $escHost = var_export($options['host'], true);
        $escPort = var_export($options['port'], true);

        $recipe = new BuildRecipe();
        $recipe->addImport('Redis');
        $recipe->addImport('Webmozart\KeyValueStore\PhpRedisStore');
        $recipe->addVarDeclaration('$client', "\$client = new Redis();\n\$client->connect($escHost, $escPort);");
        $recipe->addVarDeclaration($varName, $varName.' = new PhpRedisStore($client);');

        return $recipe;
    }
}
