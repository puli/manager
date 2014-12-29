<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Generator\Repository;

use Puli\RepositoryManager\Generator\BuildRecipe;
use Puli\RepositoryManager\Generator\BuildRecipeProvider;
use Puli\RepositoryManager\Generator\ProviderFactory;
use Webmozart\PathUtil\Path;

/**
 * Creates the build recipe for a {@link FilesystemRepository}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FilesystemRepositoryRecipeProvider implements BuildRecipeProvider
{
    /**
     * {@inheritdoc}
     */
    public function getRecipe($varName, $outputDir, $rootDir, array $options, ProviderFactory $providerFactory)
    {
        if (!isset($options['path'])) {
            $options['path'] = $outputDir.'/repository';
        }

        $path = Path::makeAbsolute($options['path'], $rootDir);
        $relPath = Path::makeRelative($path, $outputDir);

        $escPath = $relPath
            ? '__DIR__.'.var_export('/'.$relPath, true)
            : '__DIR__';

        $declaration = '';

        if ($relPath) {
            $declaration = "if (!file_exists($escPath)) {\n".
                "    mkdir($escPath, 0777, true);\n".
                "}\n\n";
        }

        $declaration .= "$varName = new FilesystemRepository($escPath);";

        $recipe = new BuildRecipe();
        $recipe->addImport('Puli\Repository\FilesystemRepository');
        $recipe->addVarDeclaration($varName, $declaration);

        return $recipe;
    }
}
