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

use Puli\Manager\Factory\Generator\KeyValueStore\ArrayStoreGenerator;
use Puli\Manager\Tests\Factory\Generator\AbstractGeneratorTest;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ArrayStoreGeneratorTest extends AbstractGeneratorTest
{
    /**
     * @var ArrayStoreGenerator
     */
    private $generator;

    protected function setUp()
    {
        parent::setUp();

        $this->generator = new ArrayStoreGenerator();
    }

    public function testGenerateService()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry);

        $expected = <<<EOF
\$store = new ArrayStore();
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testRunGeneratedCode()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry);

        $this->putCode($this->outputPath, $this->method);

        require $this->outputPath;

        $this->assertTrue(isset($store));
        $this->assertInstanceOf('Webmozart\KeyValueStore\ArrayStore', $store);
    }
}
