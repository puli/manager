<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Config;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\EnvConfig;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class EnvConfigTest extends PHPUnit_Framework_TestCase
{
    protected function tearDown()
    {
        // remove
        putenv('PULI_DIR');
    }

    public function testLoadEnvironmentVariables()
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
