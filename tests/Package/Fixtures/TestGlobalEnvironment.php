<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\Fixtures;

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFile;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFileStorage;
use Puli\RepositoryManager\Config\ConfigFile\Reader\ConfigFileReader;
use Puli\RepositoryManager\Config\ConfigFile\Writer\ConfigFileWriter;
use Puli\RepositoryManager\Environment\GlobalEnvironment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestGlobalEnvironment extends GlobalEnvironment implements ConfigFileReader, ConfigFileWriter
{
    private $configFile;

    public function __construct($homeDir, ConfigFile $configFile, EventDispatcherInterface $dispatcher)
    {
        $this->configFile = $configFile;

        parent::__construct($homeDir, new ConfigFileStorage($this, $this), $dispatcher);
    }

    public function readConfigFile($path, Config $baseConfig = null)
    {
        return $this->configFile;
    }

    public function writeConfigFile(ConfigFile $configFile, $path)
    {
    }
}
