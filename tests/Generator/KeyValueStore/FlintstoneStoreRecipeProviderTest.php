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

use Puli\RepositoryManager\Generator\KeyValueStore\FlintstoneStoreRecipeProvider;
use Puli\RepositoryManager\Tests\Generator\AbstractRecipeProviderTest;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FlintstoneStoreRecipeProviderTest extends AbstractRecipeProviderTest
{
    /**
     * @var FlintstoneStoreRecipeProvider
     */
    private $provider;

    protected function setUp()
    {
        parent::setUp();

        $this->provider = new FlintstoneStoreRecipeProvider();
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
\$store = new FlintstoneStore(
    new FlintstoneDB('data', array(
        'dir' => __DIR__.'/../',
        'ext' => '.dat',
        'gzip' => false,
        'cache' => true,
        'swap_memory_limit' => 1048576,
    ))
);
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
                'path' => $this->outputDir.'/data.dat',
            ),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$store = new FlintstoneStore(
    new FlintstoneDB('data', array(
        'dir' => __DIR__,
        'ext' => '.dat',
        'gzip' => false,
        'cache' => true,
        'swap_memory_limit' => 1048576,
    ))
);
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
\$store = new FlintstoneStore(
    new FlintstoneDB('dat\\'a', array(
        'dir' => __DIR__.'/../d\\'ir',
        'ext' => '.da\\'t',
        'gzip' => false,
        'cache' => true,
        'swap_memory_limit' => 1048576,
    ))
);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testGetRecipeWithoutSuffix()
    {
        $recipe = $this->provider->getRecipe(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array(
                'path' => 'data',
            ),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$store = new FlintstoneStore(
    new FlintstoneDB('data', array(
        'dir' => __DIR__.'/../',
        'ext' => '',
        'gzip' => false,
        'cache' => true,
        'swap_memory_limit' => 1048576,
    ))
);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testGetRecipeWithCustomOptions()
    {
        $recipe = $this->provider->getRecipe(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array(
                'gzip' => true,
                'cache' => false,
                'swapMemoryLimit' => 1234,
            ),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$store = new FlintstoneStore(
    new FlintstoneDB('data', array(
        'dir' => __DIR__.'/../',
        'ext' => '.dat',
        'gzip' => true,
        'cache' => false,
        'swap_memory_limit' => 1234,
    ))
);
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
        $this->assertInstanceOf('Webmozart\KeyValueStore\FlintstoneStore', $store);
    }
}
