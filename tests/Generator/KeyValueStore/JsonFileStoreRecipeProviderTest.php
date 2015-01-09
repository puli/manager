<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Generator\KeyValueStore;

use Puli\RepositoryManager\Generator\KeyValueStore\JsonFileStoreRecipeProvider;
use Puli\RepositoryManager\Tests\Generator\AbstractRecipeProviderTest;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class JsonFileStoreRecipeProviderTest extends AbstractRecipeProviderTest
{
    /**
     * @var JsonFileStoreRecipeProvider
     */
    private $provider;

    protected function setUp()
    {
        parent::setUp();

        $this->provider = new JsonFileStoreRecipeProvider();
    }

    public function testGetRecipe()
    {
        $recipe = $this->provider->getRecipe(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array(),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$store = new JsonFileStore(__DIR__.'/../data.json', true);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testGetRecipeInOutputDir()
    {
        $recipe = $this->provider->getRecipe(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array(
                'path' => $this->outputDir.'/data.json',
            ),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$store = new JsonFileStore(__DIR__.'/data.json', true);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testEscapeOutput()
    {
        $recipe = $this->provider->getRecipe(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array(
                'path' => 'd\'ir/dat\'a.da\'t',
            ),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$store = new JsonFileStore(__DIR__.'/../d\'ir/dat\'a.da\'t', true);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testGetRecipeWithoutCaching()
    {
        $recipe = $this->provider->getRecipe(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array(
                'cache' => false,
            ),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$store = new JsonFileStore(__DIR__.'/../data.json', false);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testRunRecipe()
    {
        $recipe = $this->provider->getRecipe(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array(),
            $this->generatorFactory
        );

        $this->putCode($this->outputPath, $recipe);

        require $this->outputPath;

        $this->assertTrue(isset($store));
        $this->assertInstanceOf('Webmozart\KeyValueStore\JsonFileStore', $store);
    }
}
