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
 * Creates the build recipe for a {@link MemcacheStore}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MemcacheStoreRecipeProvider implements BuildRecipeProvider
{
    private static $defaultOptions = array(
        'server' => '127.0.0.1',
        'port' => 11211,
    );

    /**
     * {@inheritdoc}
     */
    public function getRecipe($varName, $outputDir, $rootDir, array $options, ProviderFactory $providerFactory)
    {
        $options = array_replace(self::$defaultOptions, $options);

        $escServer = var_export($options['server'], true);
        $escPort = var_export($options['port'], true);

        $recipe = new BuildRecipe();
        $recipe->addImport('Memcache');
        $recipe->addImport('Webmozart\KeyValueStore\MemcacheStore');
        $recipe->addVarDeclaration('$memcache', <<<EOF
\$memcache = new Memcache();
\$memcache->connect($escServer, $escPort);
EOF
        );
        $recipe->addVarDeclaration($varName, $varName.' = new MemcacheStore($memcache);');

        return $recipe;
    }
}
