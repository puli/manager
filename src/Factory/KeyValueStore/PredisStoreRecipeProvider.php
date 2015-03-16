<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Factory\KeyValueStore;

use Puli\RepositoryManager\Assert\Assert;
use Puli\RepositoryManager\Factory\BuildRecipe;
use Puli\RepositoryManager\Factory\BuildRecipeProvider;
use Puli\RepositoryManager\Factory\ProviderFactory;

/**
 * Creates the build recipe for a {@link PredisStore}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PredisStoreRecipeProvider implements BuildRecipeProvider
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
        $recipe->addImport('Predis\Client');
        $recipe->addImport('Webmozart\KeyValueStore\PredisStore');
        $recipe->addVarDeclaration('$client', "\$client = new Client(array(\n    'host' => $escHost,\n    'port' => $escPort,\n));");
        $recipe->addVarDeclaration($varName, $varName.' = new PredisStore($client);');

        return $recipe;
    }
}
