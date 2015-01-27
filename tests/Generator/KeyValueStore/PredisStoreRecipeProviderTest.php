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

use Predis\Client;
use Predis\Connection\ConnectionException;
use Puli\RepositoryManager\Generator\KeyValueStore\PredisStoreRecipeProvider;
use Puli\RepositoryManager\Tests\Generator\AbstractRecipeProviderTest;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PredisStoreRecipeProviderTest extends AbstractRecipeProviderTest
{
    private static $supported;

    /**
     * @var PredisStoreRecipeProvider
     */
    private $provider;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        $client = new Client();

        try {
            $client->connect();
            $client->disconnect();
            self::$supported = true;
        } catch (ConnectionException $e) {
            self::$supported = false;
        }
    }

    protected function setUp()
    {
        if (!self::$supported) {
            $this->markTestSkipped('Predis is not available or Redis is not running.');
        }

        parent::setUp();

        $this->provider = new PredisStoreRecipeProvider();
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
\$client = new Client(array(
    'host' => '127.0.0.1',
    'port' => 6379,
));

\$store = new PredisStore(\$client);
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
\$client = new Client(array(
    'host' => 'localhost',
    'port' => 6379,
));

\$store = new PredisStore(\$client);
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
\$client = new Client(array(
    'host' => '127.0.0.1',
    'port' => 1234,
));

\$store = new PredisStore(\$client);
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
        $this->assertInstanceOf('Webmozart\KeyValueStore\PredisStore', $store);
    }
}
