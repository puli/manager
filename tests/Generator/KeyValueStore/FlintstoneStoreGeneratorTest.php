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

use Puli\RepositoryManager\Generator\KeyValueStore\FlintstoneStoreGenerator;
use Puli\RepositoryManager\Tests\Generator\AbstractGeneratorTest;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FlintstoneStoreGeneratorTest extends AbstractGeneratorTest
{
    /**
     * @var FlintstoneStoreGenerator
     */
    private $generator;

    protected function setUp()
    {
        parent::setUp();

        $this->generator = new FlintstoneStoreGenerator();
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
\$store = new FlintstoneStore(
    new FlintstoneDB('data', array(
        'dir' => __DIR__.'/../',
        'ext' => '.dat',
        'gzip' => false,
        'cache' => true,
        'swap_memory_limit' => 1048576,
    ))
);
EOF;

        $this->assertCode($expected, $code);
    }

    public function testGenerateInOutputDir()
    {
        $code = $this->generator->generateFactoryCode(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array(
                'path' => $this->outputDir.'/data.dat',
            ),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$store = new FlintstoneStore(
    new FlintstoneDB('data', array(
        'dir' => __DIR__,
        'ext' => '.dat',
        'gzip' => false,
        'cache' => true,
        'swap_memory_limit' => 1048576,
    ))
);
EOF;

        $this->assertCode($expected, $code);
    }

    public function testEscapeOutput()
    {
        $code = $this->generator->generateFactoryCode(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array(
                'path' => 'd\'ir/dat\'a.da\'t',
            ),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$store = new FlintstoneStore(
    new FlintstoneDB('dat\\'a', array(
        'dir' => __DIR__.'/../d\\'ir',
        'ext' => '.da\\'t',
        'gzip' => false,
        'cache' => true,
        'swap_memory_limit' => 1048576,
    ))
);
EOF;

        $this->assertCode($expected, $code);
    }

    public function testGenerateWithoutSuffix()
    {
        $code = $this->generator->generateFactoryCode(
            '$store',
            $this->outputDir,
            $this->rootDir,
            array(
                'path' => 'data',
            ),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$store = new FlintstoneStore(
    new FlintstoneDB('data', array(
        'dir' => __DIR__.'/../',
        'ext' => '',
        'gzip' => false,
        'cache' => true,
        'swap_memory_limit' => 1048576,
    ))
);
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
                'gzip' => true,
                'cache' => false,
                'swapMemoryLimit' => 1234,
            ),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$store = new FlintstoneStore(
    new FlintstoneDB('data', array(
        'dir' => __DIR__.'/../',
        'ext' => '.dat',
        'gzip' => true,
        'cache' => false,
        'swap_memory_limit' => 1234,
    ))
);
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
        $this->assertInstanceOf('Webmozart\KeyValueStore\FlintstoneStore', $store);
    }
}
