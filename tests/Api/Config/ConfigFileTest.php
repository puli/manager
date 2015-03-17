<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Config;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Config\ConfigFile;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigFileTest extends PHPUnit_Framework_TestCase
{
    public function provideValidPaths()
    {
        return array(
            array(null),
            array('/foo'),
        );
    }

    /**
     * @dataProvider provideValidPaths
     */
    public function testGetPath($path)
    {
        $configFile = new ConfigFile($path);

        $this->assertSame($path, $configFile->getPath());
    }

    public function provideInvalidPaths()
    {
        return array(
            array(12345),
            array(''),
        );
    }

    /**
     * @dataProvider provideInvalidPaths
     * @expectedException \InvalidArgumentException
     */
    public function testPathMustBeValid($invalidPath)
    {
        new ConfigFile($invalidPath);
    }

    public function testBaseConfig()
    {
        $baseConfig = new Config();
        $configFile = new ConfigFile('/path', $baseConfig);
        $config = $configFile->getConfig();

        $this->assertNotSame($baseConfig, $config);

        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
        $this->assertNull($config->get(Config::PULI_DIR, null, false));
    }
}
