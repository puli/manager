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

use Puli\RepositoryManager\Generator\Repository\FileCopyRepositoryGenerator;
use Puli\RepositoryManager\Tests\Generator\AbstractGeneratorTest;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FileCopyRepositoryGeneratorTest extends AbstractGeneratorTest
{
    /**
     * @var FileCopyRepositoryGenerator
     */
    private $generator;

    protected function setUp()
    {
        parent::setUp();

        $this->generator = new FileCopyRepositoryGenerator();
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
\$versionStore = new NullStore();

\$repo = new FileCopyRepository(
    __DIR__.'/repository',
    \$versionStore
);
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
                'storageDir' => $this->outputDir,
            ),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$versionStore = new NullStore();

\$repo = new FileCopyRepository(
    __DIR__,
    \$versionStore
);
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
                'storageDir' => 'my/repository',
            ),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$versionStore = new NullStore();

\$repo = new FileCopyRepository(
    __DIR__.'/../my/repository',
    \$versionStore
);
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
        $this->assertInstanceOf('Puli\Repository\FileCopyRepository', $repo);
    }
}
