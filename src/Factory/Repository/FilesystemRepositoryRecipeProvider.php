<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Factory\Repository;

use Puli\RepositoryManager\Assert\Assert;
use Puli\RepositoryManager\Factory\BuildRecipe;
use Puli\RepositoryManager\Factory\BuildRecipeProvider;
use Puli\RepositoryManager\Factory\ProviderFactory;
use Webmozart\PathUtil\Path;

/**
 * Creates the build recipe for a {@link FilesystemRepository}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FilesystemRepositoryRecipeProvider implements BuildRecipeProvider
{
    private static $defaultOptions = array(
        'symlink' => true,
    );

    /**
     * {@inheritdoc}
     */
    public function getRecipe($varName, $outputDir, $rootDir, array $options, ProviderFactory $providerFactory)
    {
        $options = array_replace(self::$defaultOptions, $options);

        if (!isset($options['path'])) {
            $options['path'] = $outputDir.'/repository';
        }

        Assert::string($options['path'], 'The "path" option should be a string. Got: %s');
        Assert::boolean($options['symlink'], 'The "symlink" option should be a boolean. Got: %s');

        $path = Path::makeAbsolute($options['path'], $rootDir);
        $relPath = Path::makeRelative($path, $outputDir);

        $escPath = $relPath
            ? '__DIR__.'.var_export('/'.$relPath, true)
            : '__DIR__';
        $escSymlink = var_export($options['symlink'], true);

        $declaration = '';

        if ($relPath) {
            $declaration = "if (!file_exists($escPath)) {\n".
                "    mkdir($escPath, 0777, true);\n".
                "}\n\n";
        }

        $declaration .= "$varName = new FilesystemRepository($escPath, $escSymlink);";

        $recipe = new BuildRecipe();
        $recipe->addImport('Puli\Repository\FilesystemRepository');
        $recipe->addVarDeclaration($varName, $declaration);

        return $recipe;
    }
}
