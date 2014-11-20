<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Package\Config;

use Puli\PackageManager\Package\Config\PackageConfig;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageConfigTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PackageConfig
     */
    private $config;

    protected function setUp()
    {
        $this->config = new PackageConfig();
    }

    public function testSetPath()
    {
        $this->assertNull($this->config->getPath());
        $this->config->setPath('/foo');
        $this->assertSame('/foo', $this->config->getPath());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetPathFailsIfNotString()
    {
        $this->config->setPath(12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetPathFailsIfEmpty()
    {
        $this->config->setPath('');
    }
}
