<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Factory\Generator\Repository;

use Puli\Manager\Factory\Generator\Repository\JsonRepositoryGenerator;
use Puli\Manager\Tests\Factory\Generator\AbstractGeneratorTest;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class JsonRepositoryGeneratorTest extends AbstractGeneratorTest
{
    /**
     * @var JsonRepositoryGenerator
     */
    private $generator;

    protected function setUp()
    {
        parent::setUp();

        $this->generator = new JsonRepositoryGenerator();
    }

    public function testGenerateService()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
        ));

        $expected = <<<EOF
\$repo = new JsonRepository(__DIR__.'/path-mappings.json', __DIR__.'/..', true);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateServiceWithPath()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'path' => 'store/repository.json',
        ));

        $expected = <<<EOF
\$repo = new JsonRepository(__DIR__.'/../store/repository.json', __DIR__.'/..', true);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateOptimizedService()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'optimize' => true,
        ));

        $expected = <<<EOF
\$stream = new JsonChangeStream(__DIR__.'/change-stream.json');
\$repo = new OptimizedJsonRepository(__DIR__.'/path-mappings.json', __DIR__.'/..', false, \$stream);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateOptimizedServiceWithPath()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'optimize' => true,
            'path' => 'store/repository.json',
        ));

        $expected = <<<EOF
\$stream = new JsonChangeStream(__DIR__.'/change-stream.json');
\$repo = new OptimizedJsonRepository(__DIR__.'/../store/repository.json', __DIR__.'/..', false, \$stream);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateOptimizedServiceWithChangeStream()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'optimize' => true,
            'change-stream' => array(
                'type' => 'key-value-store',
            ),
        ));

        $expected = <<<EOF
\$store = new JsonFileStore(__DIR__.'/change-stream.json');
\$stream = new KeyValueStoreChangeStream(\$store);
\$repo = new OptimizedJsonRepository(__DIR__.'/path-mappings.json', __DIR__.'/..', false, \$stream);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfNoRootDir()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfRootDirNoString()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'root-dir' => 1234,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfPathNoString()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'path' => 1234,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfOptimizeNoBoolean()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'optimize' => 1234,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfChangeStreamNoArray()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'change-stream' => 1234,
        ));
    }

    public function testRunGeneratedCode()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
        ));

        $this->putCode($this->outputPath, $this->method);

        require $this->outputPath;

        $this->assertTrue(isset($repo));
        $this->assertInstanceOf('Puli\Repository\JsonRepository', $repo);
    }

    public function testRunGeneratedCodeWithOptimizeOption()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'optimize' => true,
        ));

        $this->putCode($this->outputPath, $this->method);

        require $this->outputPath;

        $this->assertTrue(isset($repo));
        $this->assertInstanceOf('Puli\Repository\OptimizedJsonRepository', $repo);
    }
}
