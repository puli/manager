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

use Puli\Manager\Factory\Generator\Repository\PathMappingRepositoryGenerator;
use Puli\Manager\Tests\Factory\Generator\AbstractGeneratorTest;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PathMappingRepositoryGeneratorTest extends AbstractGeneratorTest
{
    /**
     * @var PathMappingRepositoryGenerator
     */
    private $generator;

    protected function setUp()
    {
        parent::setUp();

        $this->generator = new PathMappingRepositoryGenerator();
    }

    public function testGenerateService()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'rootDir' => $this->rootDir,
        ));

        $expected = <<<EOF
\$store = new NullStore();
\$repo = new PathMappingRepository(\$store);
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

    public function testGenerateServiceForTypeNull()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'rootDir' => $this->rootDir,
            'store' => array('type' => null),
        ));

        $expected = <<<EOF
\$store = new NullStore();
\$repo = new PathMappingRepository(\$store);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testRunGeneratedCode()
    {
        $this->generator->generateNewInstance('repo', $this->method, $this->registry, array(
            'rootDir' => $this->rootDir,
        ));

        $this->putCode($this->outputPath, $this->method);

        require $this->outputPath;

        $this->assertTrue(isset($repo));
        $this->assertInstanceOf('Puli\Repository\PathMappingRepository', $repo);
    }
}
