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

use Puli\RepositoryManager\Generator\Discovery\KeyValueStoreDiscoveryGenerator;
use Puli\RepositoryManager\Tests\Generator\AbstractDiscoveryGeneratorTest;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class KeyValueStoreDiscoveryGeneratorTest extends AbstractDiscoveryGeneratorTest
{
    /**
     * @var KeyValueStoreDiscoveryGenerator
     */
    private $generator;

    protected function setUp()
    {
        parent::setUp();

        $this->generator = new KeyValueStoreDiscoveryGenerator();
    }

    public function testGenerate()
    {
        $code = $this->generator->generateFactoryCode(
            '$discovery',
            $this->outputDir,
            $this->rootDir,
            array(),
            $this->generatorFactory
        );

        $expected = <<<EOF
\$store = new NullStore();

\$discovery = new KeyValueStoreDiscovery(
    \$repo,
    \$store
);
EOF;

        $this->assertCode($expected, $code);
    }

    public function testRunGeneratedCode()
    {
        $code = $this->generator->generateFactoryCode(
            '$discovery',
            $this->outputDir,
            $this->rootDir,
            array(),
            $this->generatorFactory
        );

        $this->putCode($this->outputPath, $code);

        require $this->outputPath;

        $this->assertTrue(isset($discovery));
        $this->assertInstanceOf('Puli\Discovery\KeyValueStoreDiscovery', $discovery);
    }
}
