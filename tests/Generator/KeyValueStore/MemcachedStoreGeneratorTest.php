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

use Puli\RepositoryManager\Generator\KeyValueStore\MemcachedStoreGenerator;
use Puli\RepositoryManager\Tests\Generator\AbstractGeneratorTest;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MemcachedStoreGeneratorTest extends AbstractGeneratorTest
{
    private static $supported;

    /**
     * @var MemcachedStoreGenerator
     */
    private $generator;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        if (!class_exists('\Memcached')) {
            self::$supported = false;

            return;
        }

        self::$supported = true;
    }

    protected function setUp()
    {
        parent::setUp();

        $this->generator = new MemcachedStoreGenerator();
    }

    public function testGenerate()
    {
        $code = $this->generator->generateFactoryCode(
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

        $this->assertCode($expected, $code);
    }

    public function testGenerateWithCustomOptions()
    {
        $code = $this->generator->generateFactoryCode(
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

        $this->assertCode($expected, $code);
    }

    public function testRunGeneratedCode()
    {
        if (!self::$supported) {
            $this->markTestSkipped('Memcached is not supported');

            return;
        }

        $code = $this->generator->generateFactoryCode(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array(),
            $this->generatorFactory
        );

        $this->putCode($this->outputPath, $code);

        require $this->outputPath;

        $this->assertTrue(isset($store));
        $this->assertInstanceOf('Webmozart\KeyValueStore\MemcachedStore', $store);
    }
}
