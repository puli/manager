<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Factory\KeyValueStore;

use Basho\Riak\Riak;
use Puli\RepositoryManager\Factory\KeyValueStore\RiakStoreRecipeProvider;
use Puli\RepositoryManager\Tests\Factory\AbstractRecipeProviderTest;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RiakStoreRecipeProviderTest extends AbstractRecipeProviderTest
{
    private static $supported;

    /**
     * @var RiakStoreRecipeProvider
     */
    private $provider;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        $client = new Riak();

        self::$supported = $client->isAlive();
    }

    protected function setUp()
    {
        if (!self::$supported) {
            $this->markTestSkipped('Riak is not available or Redis is not running.');
        }

        parent::setUp();

        $this->provider = new RiakStoreRecipeProvider();
    }

    public function testGetRecipe()
    {
        $recipe = $this->provider->getRecipe(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array('bucket' => 'puli'),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$client = new Riak('127.0.0.1', 8098);

\$store = new RiakStore('puli', \$client);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testGetRecipeWithCustomHost()
    {
        $recipe = $this->provider->getRecipe(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array('bucket' => 'puli', 'host' => 'localhost'),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$client = new Riak('localhost', 8098);

\$store = new RiakStore('puli', \$client);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testGetRecipeWithCustomPort()
    {
        $recipe = $this->provider->getRecipe(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array('bucket' => 'puli', 'port' => 1234),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$client = new Riak('127.0.0.1', 1234);

\$store = new RiakStore('puli', \$client);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testRunRecipe()
    {
        $recipe = $this->provider->getRecipe(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array('bucket' => 'puli'),
            $this->generatorFactory
        );

        $this->putCode($this->outputPath, $recipe);

        require $this->outputPath;

        $this->assertTrue(isset($store));
        $this->assertInstanceOf('Webmozart\KeyValueStore\RiakStore', $store);
    }
}
