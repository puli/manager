<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Factory\Generator\ChangeStream;

use Puli\Manager\Factory\Generator\ChangeStream\KeyValueStoreChangeStreamGenerator;
use Puli\Manager\Tests\Factory\Generator\AbstractGeneratorTest;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class KeyValueStoreChangeStreamGeneratorTest extends AbstractGeneratorTest
{
    /**
     * @var KeyValueStoreChangeStreamGenerator
     */
    private $generator;

    protected function setUp()
    {
        parent::setUp();

        $this->generator = new KeyValueStoreChangeStreamGenerator();
    }

    public function testGenerateService()
    {
        $this->generator->generateNewInstance('stream', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
        ));

        $expected = <<<'EOF'
$store = new JsonFileStore(__DIR__.'/change-stream.json');
$stream = new KeyValueStoreChangeStream($store);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateServiceForTypeNull()
    {
        $this->generator->generateNewInstance('stream', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'store' => array('type' => null),
        ));

        $expected = <<<'EOF'
$store = new NullStore();
$stream = new KeyValueStoreChangeStream($store);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfNoRootDir()
    {
        $this->generator->generateNewInstance('stream', $this->method, $this->registry);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfRootDirNoString()
    {
        $this->generator->generateNewInstance('stream', $this->method, $this->registry, array(
            'root-dir' => 1234,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfStoreNotArray()
    {
        $this->generator->generateNewInstance('stream', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'store' => 1234,
        ));
    }

    public function testRunGeneratedCode()
    {
        $this->generator->generateNewInstance('stream', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
        ));

        $this->putCode($this->outputPath, $this->method);

        require $this->outputPath;

        $this->assertTrue(isset($stream));
        $this->assertInstanceOf('Puli\Repository\ChangeStream\KeyValueStoreChangeStream', $stream);
    }
}
