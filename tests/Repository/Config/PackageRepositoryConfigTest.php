<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Repository\Config;

use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageRepositoryConfigTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PackageRepositoryConfig
     */
    private $config;

    protected function setUp()
    {
        $this->config = new PackageRepositoryConfig();
    }

    public function testGetPath()
    {
        $this->assertNull($this->config->getPath());

        $this->config = new PackageRepositoryConfig('/foo');
        $this->assertSame('/foo', $this->config->getPath());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testPathMustBeString()
    {
        new PackageRepositoryConfig(12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testPathMustNotBeEmpty()
    {
        new PackageRepositoryConfig('');
    }
}
