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

use Puli\RepositoryManager\Generator\BuildRecipe;
use Puli\RepositoryManager\Generator\BuildRecipeProvider;
use Puli\RepositoryManager\Generator\ProviderFactory;

/**
 * Creates the build recipe for an {@link ArrayStore}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ArrayStoreRecipeProvider implements BuildRecipeProvider
{
    /**
     * {@inheritdoc}
     */
    public function getRecipe($varName, $outputDir, $rootDir, array $options, ProviderFactory $providerFactory)
    {
        $recipe = new BuildRecipe();
        $recipe->addImport('Webmozart\KeyValueStore\ArrayStore');
        $recipe->addVarDeclaration($varName, $varName.' = new ArrayStore();');

        return $recipe;
    }
}
