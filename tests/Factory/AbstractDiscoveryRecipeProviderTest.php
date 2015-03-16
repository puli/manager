<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Factory;

use Puli\RepositoryManager\Factory\BuildRecipe;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractDiscoveryRecipeProviderTest extends AbstractRecipeProviderTest
{
    protected function putCode($path, BuildRecipe $recipe)
    {
        // In the generated class, the repository is passed as argument.
        // Create a repository here so that we can run the code successfully.
        $recipeWithRepo = new BuildRecipe();
        $recipeWithRepo->addImport('Puli\RepositoryManager\Tests\Factory\Fixtures\TestRepository');
        $recipeWithRepo->addImports($recipe->getImports());
        $recipeWithRepo->addVarDeclaration('$repo', '$repo = new TestRepository();');
        $recipeWithRepo->addVarDeclarations($recipe->getVarDeclarations());

        parent::putCode($path, $recipeWithRepo);
    }
}
