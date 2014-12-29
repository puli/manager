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

use Puli\RepositoryManager\Generator\KeyValueStore\NullStoreGenerator;
use Puli\RepositoryManager\Tests\Generator\AbstractGeneratorTest;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NullStoreGeneratorTest extends AbstractGeneratorTest
{
    /**
     * @var NullStoreGenerator
     */
    private $generator;

    protected function setUp()
    {
        parent::setUp();

        $this->generator = new NullStoreGenerator();
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
\$store = new NullStore();
EOF;

        $this->assertCode($expected, $code);
    }

    public function testRunGeneratedCode()
    {
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
        $this->assertInstanceOf('Webmozart\KeyValueStore\NullStore', $store);
    }
}
