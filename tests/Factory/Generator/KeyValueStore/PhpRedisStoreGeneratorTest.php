<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Factory\Generator\KeyValueStore;

use Puli\Manager\Factory\Generator\KeyValueStore\PhpRedisStoreGenerator;
use Puli\Manager\Tests\Factory\Generator\AbstractGeneratorTest;
use Redis;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PhpRedisStoreGeneratorTest extends AbstractGeneratorTest
{
    private static $supported;

    /**
     * @var PhpRedisStoreGenerator
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

        $this->provider = new PhpRedisStoreGenerator();
    }

    public function testGenerateService()
    {
        $this->provider->generateNewInstance('store', $this->method, $this->registry);

        $expected = <<<EOF
\$client = new Redis();
\$client->connect('127.0.0.1', 6379);
\$store = new PhpRedisStore(\$client);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateServiceWithCustomHost()
    {
        $this->provider->generateNewInstance('store', $this->method, $this->registry, array(
            'host' => 'localhost',
        ));

        $expected = <<<EOF
\$client = new Redis();
\$client->connect('localhost', 6379);
\$store = new PhpRedisStore(\$client);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateServiceWithCustomPort()
    {
        $this->provider->generateNewInstance('store', $this->method, $this->registry, array(
            'port' => 1234,
        ));

        $expected = <<<EOF
\$client = new Redis();
\$client->connect('127.0.0.1', 1234);
\$store = new PhpRedisStore(\$client);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testRunGeneratedCode()
    {
        $this->provider->generateNewInstance('store', $this->method, $this->registry);

        $this->putCode($this->outputPath, $this->method);

        require $this->outputPath;

        $this->assertTrue(isset($store));
        $this->assertInstanceOf('Webmozart\KeyValueStore\PhpRedisStore', $store);
    }
}
