<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Config;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Config\EnvConfig;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class EnvConfigTest extends PHPUnit_Framework_TestCase
{
    protected function tearDown()
    {
        // remove
        putenv('PULI_DIR');
    }

    public function testLoadContextVariables()
    {
        putenv('PULI_DIR=my-puli-dir');

        $default = new Config();
        $default->set(Config::PULI_DIR, 'default');
        $config = new EnvConfig($default);

        $this->assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
    }

    public function testReturnDefaultIfNotSet()
    {
        $default = new Config();
        $default->set(Config::PULI_DIR, 'default');
        $config = new EnvConfig($default);

        $this->assertSame('default', $config->get(Config::PULI_DIR));
    }
}
