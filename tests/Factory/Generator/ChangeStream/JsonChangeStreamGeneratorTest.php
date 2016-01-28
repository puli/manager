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

use Puli\Manager\Factory\Generator\ChangeStream\JsonChangeStreamGenerator;
use Puli\Manager\Tests\Factory\Generator\AbstractGeneratorTest;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class JsonChangeStreamGeneratorTest extends AbstractGeneratorTest
{
    /**
     * @var JsonChangeStreamGenerator
     */
    private $generator;

    protected function setUp()
    {
        parent::setUp();

        $this->generator = new JsonChangeStreamGenerator();
    }

    public function testGenerateService()
    {
        $this->generator->generateNewInstance('stream', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
        ));

        $expected = <<<'EOF'
$stream = new JsonChangeStream(__DIR__.'/change-stream.json');
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateServiceWithPath()
    {
        $this->generator->generateNewInstance('stream', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'path' => 'change/stream.json',
        ));

        $expected = <<<'EOF'
$stream = new JsonChangeStream(__DIR__.'/../change/stream.json');
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

    public function testRunGeneratedCode()
    {
        $this->generator->generateNewInstance('stream', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
        ));

        $this->putCode($this->outputPath, $this->method);

        require $this->outputPath;

        $this->assertTrue(isset($stream));
        $this->assertInstanceOf('Puli\Repository\ChangeStream\JsonChangeStream', $stream);
    }
}
