<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Generator\Discovery;

use Puli\RepositoryManager\Generator\BuildRecipe;
use Puli\RepositoryManager\Generator\BuildRecipeProvider;
use Puli\RepositoryManager\Generator\ProviderFactory;

/**
 * Creates the build recipe for a {@link KeyValueStoreDiscovery}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class KeyValueStoreDiscoveryRecipeProvider implements BuildRecipeProvider
{
    private static $defaultOptions = array(
        'store' => array(
            'type' => 'null',
        )
    );

    /**
     * {@inheritdoc}
     */
    public function getRecipe($varName, $outputDir, $rootDir, array $options, ProviderFactory $providerFactory)
    {
        $options = array_replace_recursive(self::$defaultOptions, $options);

        $storeRecipeProvider = $providerFactory->createKeyValueStoreRecipeProvider($options['store']['type']);
        $storeRecipe = $storeRecipeProvider->getRecipe(
            '$store',
            $outputDir,
            $rootDir,
            $options['store'],
            $providerFactory
        );

        $recipe = new BuildRecipe();
        $recipe->addImports($storeRecipe->getImports());
        $recipe->addVarDeclarations($storeRecipe->getVarDeclarations());

        $recipe->addImport('Puli\Discovery\KeyValueStoreDiscovery');
        $recipe->addVarDeclaration($varName, <<<EOF
$varName = new KeyValueStoreDiscovery(
    \$repo,
    \$store
);
EOF
        );

        return $recipe;
    }
}
