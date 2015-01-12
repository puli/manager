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

use Memcached;
use Puli\RepositoryManager\Generator\KeyValueStore\MemcachedStoreRecipeProvider;
use Puli\RepositoryManager\Tests\Generator\AbstractRecipeProviderTest;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MemcachedStoreRecipeProviderTest extends AbstractRecipeProviderTest
{
    private static $supported;

    /**
     * @var MemcachedStoreRecipeProvider
     */
    private $provider;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        if (!class_exists('\Memcached')) {
            self::$supported = false;

            return;
        }

        // try to connect
        $memcached = new Memcached();
        $memcached->addServer('127.0.0.1', 11211);
        $memcached->get('foobar');

        if (Memcached::RES_NOTFOUND !== $memcached->getResultCode()) {
            self::$supported = false;

            return;
        }

        self::$supported = true;
    }

    protected function setUp()
    {
        parent::setUp();

        $this->provider = new MemcachedStoreRecipeProvider();
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
\$memcached = new Memcached();
\$memcached->addServer('127.0.0.1', 11211);

\$store = new MemcachedStore(\$memcached);
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
\$memcached = new Memcached();
\$memcached->addServer('localhost', 1234);

\$store = new MemcachedStore(\$memcached);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testRunRecipe()
    {
        if (!self::$supported) {
            $this->markTestSkipped('Memcached is not supported');

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
        $this->assertInstanceOf('Webmozart\KeyValueStore\MemcachedStore', $store);
    }
}
