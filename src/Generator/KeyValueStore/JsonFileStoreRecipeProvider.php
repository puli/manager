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
use Webmozart\PathUtil\Path;

/**
 * Creates the build recipe for a {@link JsonFileStore}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class JsonFileStoreRecipeProvider implements BuildRecipeProvider
{
    private static $defaultOptions = array(
        'path' => 'data.json',
        'cache' => true,
    );

    /**
     * {@inheritdoc}
     */
    public function getRecipe($varName, $outputDir, $rootDir, array $options, ProviderFactory $providerFactory)
    {
        $options = array_replace(self::$defaultOptions, $options);

        $recipe = new BuildRecipe();
        $recipe->addImport('Webmozart\KeyValueStore\JsonFileStore');

        $path = Path::makeAbsolute($options['path'], $rootDir);
        $relPath = Path::makeRelative($path, $outputDir);
        $escRelPath = '__DIR__.'.var_export('/'.$relPath, true);
        $escCache = $options['cache'] ? 'true' : 'false';

        $recipe->addVarDeclaration($varName, <<<EOF
$varName = new JsonFileStore($escRelPath, $escCache);
EOF
        );

        return $recipe;
    }
}
