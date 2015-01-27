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

use Puli\RepositoryManager\Generator\KeyValueStore\PhpRedisStoreRecipeProvider;
use Puli\RepositoryManager\Tests\Generator\AbstractRecipeProviderTest;
use Redis;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PhpRedisStoreRecipeProviderTest extends AbstractRecipeProviderTest
{
    private static $supported;

    /**
     * @var PhpRedisStoreRecipeProvider
     */
    private $provider;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        if (!class_exists('\Redis', false)) {
            self::$supported = false;

            return;
        }

        $redis = new Redis();

        self::$supported = @$redis->connect('127.0.0.1', 6379);
    }

    protected function setUp()
    {
        if (!self::$supported) {
            $this->markTestSkipped('PhpRedis is not available or Redis is not running.');
        }

        parent::setUp();

        $this->provider = new PhpRedisStoreRecipeProvider();
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
\$client = new Redis();
\$client->connect('127.0.0.1', 6379);

\$store = new PhpRedisStore(\$client);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testGetRecipeWithCustomHost()
    {
        $recipe = $this->provider->getRecipe(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array('host' => 'localhost'),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$client = new Redis();
\$client->connect('localhost', 6379);

\$store = new PhpRedisStore(\$client);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testGetRecipeWithCustomPort()
    {
        $recipe = $this->provider->getRecipe(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array('port' => 1234),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$client = new Redis();
\$client->connect('127.0.0.1', 1234);

\$store = new PhpRedisStore(\$client);
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
        $this->assertInstanceOf('Webmozart\KeyValueStore\PhpRedisStore', $store);
    }
}
