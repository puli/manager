<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Generator\Discovery;

use Puli\RepositoryManager\Generator\Repository\FilesystemRepositoryGenerator;
use Puli\RepositoryManager\Tests\Generator\AbstractGeneratorTest;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FilesystemRepositoryGeneratorTest extends AbstractGeneratorTest
{
    /**
     * @var FilesystemRepositoryGenerator
     */
    private $generator;

    protected function setUp()
    {
        parent::setUp();

        $this->generator = new FilesystemRepositoryGenerator();
    }

    public function testGenerate()
    {
        $code = $this->generator->generateFactoryCode(
            '$repo',
            $this->outputDir,
            $this->rootDir,
            array(),
            $this->generatorFactory
        );

        $expected = <<<EOF
if (!file_exists(__DIR__.'/repository')) {
    mkdir(__DIR__.'/repository', 0777, true);
}

\$repo = new FilesystemRepository(__DIR__.'/repository');
EOF;

        $this->assertCode($expected, $code);
    }

    public function testGenerateInOutputDir()
    {
        $code = $this->generator->generateFactoryCode(
            '$repo',
            $this->outputDir,
            $this->rootDir,
            array(
                'path' => $this->outputDir,
            ),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$repo = new FilesystemRepository(__DIR__);
EOF;

        $this->assertCode($expected, $code);
    }

    public function testGenerateInCustomDir()
    {
        $code = $this->generator->generateFactoryCode(
            '$repo',
            $this->outputDir,
            $this->rootDir,
            array(
                'path' => 'my/repository',
            ),
            $this->generatorFactory
        );

        $expected = <<<EOF
if (!file_exists(__DIR__.'/../my/repository')) {
    mkdir(__DIR__.'/../my/repository', 0777, true);
}

\$repo = new FilesystemRepository(__DIR__.'/../my/repository');
EOF;

        $this->assertCode($expected, $code);
    }

    public function testRunGeneratedCode()
    {
        $code = $this->generator->generateFactoryCode(
            '$repo',
            $this->outputDir,
            $this->rootDir,
            array(),
            $this->generatorFactory
        );

        $this->putCode($this->outputPath, $code);

        require $this->outputPath;

        $this->assertTrue(isset($repo));
        $this->assertInstanceOf('Puli\Repository\FilesystemRepository', $repo);
    }
}
