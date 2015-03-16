<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Factory\Discovery;

use Puli\RepositoryManager\Factory\Repository\FilesystemRepositoryRecipeProvider;
use Puli\RepositoryManager\Tests\Factory\AbstractRecipeProviderTest;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FilesystemRepositoryRecipeProviderTest extends AbstractRecipeProviderTest
{
    /**
     * @var FilesystemRepositoryRecipeProvider
     */
    private $provider;

    protected function setUp()
    {
        parent::setUp();

        $this->provider = new FilesystemRepositoryRecipeProvider();
    }

    public function testGetRecipe()
    {
        $recipe = $this->provider->getRecipe(
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

\$repo = new FilesystemRepository(__DIR__.'/repository', true);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testGetRecipeInOutputDir()
    {
        $recipe = $this->provider->getRecipe(
            '$repo',
            $this->outputDir,
            $this->rootDir,
            array('path' => $this->outputDir),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$repo = new FilesystemRepository(__DIR__, true);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testGetRecipeInCustomDir()
    {
        $recipe = $this->provider->getRecipe(
            '$repo',
            $this->outputDir,
            $this->rootDir,
            array('path' => 'my/repository'),
            $this->generatorFactory
        );

        $expected = <<<EOF
if (!file_exists(__DIR__.'/../my/repository')) {
    mkdir(__DIR__.'/../my/repository', 0777, true);
}

\$repo = new FilesystemRepository(__DIR__.'/../my/repository', true);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testGetRecipeWithSymlinkTrue()
    {
        $recipe = $this->provider->getRecipe(
            '$repo',
            $this->outputDir,
            $this->rootDir,
            array('symlink' => true),
            $this->generatorFactory
        );

        $expected = <<<EOF
if (!file_exists(__DIR__.'/repository')) {
    mkdir(__DIR__.'/repository', 0777, true);
}

\$repo = new FilesystemRepository(__DIR__.'/repository', true);
EOF;

        $this->assertCode($expected, $recipe);
    }

    public function testGetRecipeWithSymlinkFalse()
    {
        $recipe = $this->provider->getRecipe(
            '$repo',
            $this->outputDir,
            $this->rootDir,
            array('symlink' => false),
            $this->generatorFactory
        );

        $expected = <<<EOF
if (!file_exists(__DIR__.'/repository')) {
    mkdir(__DIR__.'/repository', 0777, true);
}

\$repo = new FilesystemRepository(__DIR__.'/repository', false);
EOF;

        $this->assertCode($expected, $recipe);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetRecipeFailsIfPathNotString()
    {
        $this->provider->getRecipe(
            '$repo',
            $this->outputDir,
            $this->rootDir,
            array('path' => 1234),
            $this->generatorFactory
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetRecipeFailsIfSymlinkNotBoolean()
    {
        $this->provider->getRecipe(
            '$repo',
            $this->outputDir,
            $this->rootDir,
            array('symlink' => 'true'),
            $this->generatorFactory
        );
    }

    public function testRunRecipe()
    {
        $recipe = $this->provider->getRecipe(
            '$repo',
            $this->outputDir,
            $this->rootDir,
            array(),
            $this->generatorFactory
        );

        $this->putCode($this->outputPath, $recipe);

        require $this->outputPath;

        $this->assertTrue(isset($repo));
        $this->assertInstanceOf('Puli\Repository\FilesystemRepository', $repo);
    }
}
