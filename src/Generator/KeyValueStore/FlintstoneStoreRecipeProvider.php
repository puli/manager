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
 * Creates the build recipe for a {@link FlintstoneStore}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FlintstoneStoreRecipeProvider implements BuildRecipeProvider
{
    private static $defaultOptions = array(
        'path' => 'data.dat',
        'gzip' => false,
        'cache' => true,
        'swapMemoryLimit' => 1048576,
    );

    /**
     * {@inheritdoc}
     */
    public function getRecipe($varName, $outputDir, $rootDir, array $options, ProviderFactory $providerFactory)
    {
        $options = array_replace(self::$defaultOptions, $options);

        $recipe = new BuildRecipe();
        $recipe->addImport('Webmozart\KeyValueStore\FlintstoneStore');
        $recipe->addImport('Flintstone\FlintstoneDB');

        $dbPath = Path::makeAbsolute($options['path'], $rootDir);
        $dbExtension = pathinfo($dbPath, PATHINFO_EXTENSION);

        // pathinfo() does not include the leading dot that we need
        if ($dbExtension) {
            $dbExtension = '.'.$dbExtension;
        }

        $dbDir = Path::getDirectory($dbPath);
        $relDbDir = Path::makeRelative($dbDir, $outputDir);

        $escDbName = var_export(basename($dbPath, $dbExtension), true);
        $escDbExt = var_export($dbExtension, true);
        $escDbDir = $relDbDir
            ? '__DIR__.'.var_export('/'.$relDbDir, true)
            : '__DIR__';
        $escGzip = $options['gzip'] ? 'true' : 'false';
        $escCache = $options['cache'] ? 'true' : 'false';
        $escMemLimit = var_export($options['swapMemoryLimit'], true);

        $recipe->addVarDeclaration($varName, <<<EOF
$varName = new FlintstoneStore(
    new FlintstoneDB($escDbName, array(
        'dir' => $escDbDir,
        'ext' => $escDbExt,
        'gzip' => $escGzip,
        'cache' => $escCache,
        'swap_memory_limit' => $escMemLimit,
    ))
);
EOF
        );

        return $recipe;
    }
}
