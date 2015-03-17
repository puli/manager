<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Factory\Generator\Discovery;

use Puli\Manager\Factory\Generator\Repository\FilesystemRepositoryGenerator;
use Puli\Manager\Tests\Factory\Generator\AbstractGeneratorTest;

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

    public function testGenerateService()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'rootDir' => $this->rootDir,
        ));

        $expected = <<<EOF
if (!file_exists(__DIR__.'/repository')) {
    mkdir(__DIR__.'/repository', 0777, true);
}

\$repo = new FilesystemRepository(__DIR__.'/repository', true);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateServiceInOutputDir()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'rootDir' => $this->rootDir,
            'path' => $this->outputDir,
        ));

        $expected = <<<EOF
\$repo = new FilesystemRepository(__DIR__, true);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateServiceInCustomDir()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'rootDir' => $this->rootDir,
            'path' => 'my/repository',
        ));

        $expected = <<<EOF
if (!file_exists(__DIR__.'/../my/repository')) {
    mkdir(__DIR__.'/../my/repository', 0777, true);
}

\$repo = new FilesystemRepository(__DIR__.'/../my/repository', true);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateServiceWithSymlinkTrue()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'rootDir' => $this->rootDir,
            'symlink' => true,
        ));

        $expected = <<<EOF
if (!file_exists(__DIR__.'/repository')) {
    mkdir(__DIR__.'/repository', 0777, true);
}

\$repo = new FilesystemRepository(__DIR__.'/repository', true);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateServiceWithSymlinkFalse()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'rootDir' => $this->rootDir,
            'symlink' => false,
        ));

        $expected = <<<EOF
if (!file_exists(__DIR__.'/repository')) {
    mkdir(__DIR__.'/repository', 0777, true);
}

\$repo = new FilesystemRepository(__DIR__.'/repository', false);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfPathNotString()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'rootDir' => $this->rootDir,
            'path' => 1234,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfSymlinkNotBoolean()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'rootDir' => $this->rootDir,
            'symlink' => 'true',
        ));
    }

    public function testRunGeneratedCode()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'rootDir' => $this->rootDir,
        ));

        $this->putCode($this->outputPath, $this->method);

        require $this->outputPath;

        $this->assertTrue(isset($repo));
        $this->assertInstanceOf('Puli\Repository\FilesystemRepository', $repo);
    }
}
