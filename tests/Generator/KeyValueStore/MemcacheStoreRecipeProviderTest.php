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

use Memcache;
use Puli\RepositoryManager\Generator\KeyValueStore\MemcacheStoreRecipeProvider;
use Puli\RepositoryManager\Tests\Generator\AbstractRecipeProviderTest;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MemcacheStoreRecipeProviderTest extends AbstractRecipeProviderTest
{
    private static $supported;

    /**
     * @var MemcacheStoreRecipeProvider
     */
    private $provider;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        if (!class_exists('\Memcache')) {
            self::$supported = false;

            return;
        }

        $client = new Memcache();

        self::$supported = @$client->connect('127.0.0.1');
    }

    protected function setUp()
    {
        parent::setUp();

        $this->provider = new MemcacheStoreRecipeProvider();
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
\$memcache = new Memcache();
\$memcache->connect('127.0.0.1', 11211);

\$store = new MemcacheStore(\$memcache);
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
                'server' => 'localhost',
                'port' => 1234,
            ),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$memcache = new Memcache();
\$memcache->connect('localhost', 1234);

\$store = new MemcacheStore(\$memcache);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testRunRecipe()
    {
        if (!self::$supported) {
            $this->markTestSkipped('Memcache is not supported');

            return;
        }

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
        $this->assertInstanceOf('Webmozart\KeyValueStore\MemcacheStore', $store);
    }
}
