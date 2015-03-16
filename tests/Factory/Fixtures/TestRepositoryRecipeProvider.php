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
class TestRepositoryRecipeProvider implements BuildRecipeProvider
{
    public function getRecipe($varName, $outputDir, $rootDir, array $options, ProviderFactory $providerFactory)
    {
        $recipe = new BuildRecipe();
        $recipe->addImport(__NAMESPACE__.'\TestRepository');
        $recipe->addVarDeclaration('$path', '$path = "'.$options['path'].'";');
        $recipe->addVarDeclaration($varName, $varName.' = new TestRepository($path);');

        // Test global imports
        // Global imports need to be filtered when placing code in the global
        // namespace, otherwise PHP creates a fatal error
        $recipe->addImport('Traversable');

        return $recipe;
    }
}
