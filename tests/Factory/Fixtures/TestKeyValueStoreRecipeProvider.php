<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Factory\Fixtures;

use Puli\RepositoryManager\Factory\BuildRecipe;
use Puli\RepositoryManager\Factory\BuildRecipeProvider;
use Puli\RepositoryManager\Factory\ProviderFactory;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestKeyValueStoreRecipeProvider implements BuildRecipeProvider
{
    public function getRecipe($varName, $outputDir, $rootDir, array $options, ProviderFactory $providerFactory)
    {
        $recipe = new BuildRecipe();
        $recipe->addImport('Webmozart\KeyValueStore\Impl\NullStore');
        $recipe->addVarDeclaration($varName, $varName.' = new NullStore();');

        return $recipe;
    }
}
