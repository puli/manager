<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Factory\Discovery;

use Puli\RepositoryManager\Factory\Discovery\KeyValueStoreDiscoveryRecipeProvider;
use Puli\RepositoryManager\Tests\Factory\AbstractDiscoveryRecipeProviderTest;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class KeyValueStoreDiscoveryRecipeProviderTest extends AbstractDiscoveryRecipeProviderTest
{
    /**
     * @var KeyValueStoreDiscoveryRecipeProvider
     */
    private $provider;

    protected function setUp()
    {
        parent::setUp();

        $this->provider = new KeyValueStoreDiscoveryRecipeProvider();
    }

    public function testGetRecipe()
    {
        $recipe = $this->provider->getRecipe(
            '$discovery',
            $this->outputDir,
            $this->rootDir,
            array(),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$store = new NullStore();

\$discovery = new KeyValueStoreDiscovery(
    \$repo,
    \$store
);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testGetRecipeForTypeNull()
    {
        $recipe = $this->provider->getRecipe(
            '$discovery',
            $this->outputDir,
            $this->rootDir,
            array('store' => array('type' => null)),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$store = new NullStore();

\$discovery = new KeyValueStoreDiscovery(
    \$repo,
    \$store
);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testRunRecipe()
    {
        $recipe = $this->provider->getRecipe(
            '$discovery',
            $this->outputDir,
            $this->rootDir,
            array(),
            $this->generatorFactory
        );

        $this->putCode($this->outputPath, $recipe);

        require $this->outputPath;

        $this->assertTrue(isset($discovery));
        $this->assertInstanceOf('Puli\Discovery\KeyValueStoreDiscovery', $discovery);
    }
}
