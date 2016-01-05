<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Factory\Generator\KeyValueStore;

use Puli\Manager\Factory\Generator\KeyValueStore\JsonFileStoreGenerator;
use Puli\Manager\Tests\Factory\Generator\AbstractGeneratorTest;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class JsonFileStoreGeneratorTest extends AbstractGeneratorTest
{
    /**
     * @var JsonFileStoreGenerator
     */
    private $generator;

    protected function setUp()
    {
        parent::setUp();

        $this->generator = new JsonFileStoreGenerator();
    }

    public function testGenerateService()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
        ));

        $expected = <<<EOF
\$store = new JsonFileStore(__DIR__.'/../data.json');
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfRootDirMissing()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry);
    }

    public function testGenerateServiceInOutputDir()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'path' => $this->outputDir.'/data.json',
        ));

        $expected = <<<EOF
\$store = new JsonFileStore(__DIR__.'/data.json');
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testEscapeOutput()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'path' => 'd\'ir/dat\'a.da\'t',
        ));

        $expected = <<<EOF
\$store = new JsonFileStore(__DIR__.'/../d\'ir/dat\'a.da\'t');
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfNoRootDir()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfRootDirNoString()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'root-dir' => 1234,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfPathNoString()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'path' => 1234,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfSerializeStringsNoBoolean()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'serialize-strings' => 1234,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfSerializeArraysNoBoolean()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'serialize-arrays' => 1234,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfEscapeSlashNoBoolean()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'escape-slash' => 1234,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfPrettyPrintNoBoolean()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'pretty-print' => 1234,
        ));
    }

    public function testRunGeneratedCode()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
        ));

        $this->putCode($this->outputPath, $this->method);

        require $this->outputPath;

        $this->assertTrue(isset($store));
        $this->assertInstanceOf('Webmozart\KeyValueStore\JsonFileStore', $store);
    }
}
