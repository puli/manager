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

use Basho\Riak\Riak;
use Puli\Manager\Factory\Generator\KeyValueStore\RiakStoreGenerator;
use Puli\Manager\Tests\Factory\Generator\AbstractGeneratorTest;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RiakStoreGeneratorTest extends AbstractGeneratorTest
{
    private static $supported;

    /**
     * @var RiakStoreGenerator
     */
    private $generator;

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

        $this->generator = new RiakStoreGenerator();
    }

    public function testGenerateService()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'bucket' => 'puli',
        ));

        $expected = <<<'EOF'
$client = new Riak('127.0.0.1', 8098);
$store = new RiakStore('puli', $client);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateServiceWithCustomHost()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'bucket' => 'puli',
            'host' => 'localhost',
        ));

        $expected = <<<'EOF'
$client = new Riak('localhost', 8098);
$store = new RiakStore('puli', $client);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateServiceWithCustomPort()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'bucket' => 'puli',
            'port' => 1234,
        ));

        $expected = <<<'EOF'
$client = new Riak('127.0.0.1', 1234);
$store = new RiakStore('puli', $client);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfNoBucket()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfBucketNoString()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'bucket' => 1234,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfHostNoString()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'bucket' => 'the-bucket',
            'host' => 1234,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfPortNoInteger()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'bucket' => 'the-bucket',
            'port' => false,
        ));
    }

    public function testRunGeneratedCode()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'bucket' => 'puli',
        ));

        $this->putCode($this->outputPath, $this->method);

        require $this->outputPath;

        $this->assertTrue(isset($store));
        $this->assertInstanceOf('Webmozart\KeyValueStore\RiakStore', $store);
    }
}
