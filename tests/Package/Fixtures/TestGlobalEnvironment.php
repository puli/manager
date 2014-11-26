<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\Fixtures;

use Puli\RepositoryManager\Config\GlobalConfig;
use Puli\RepositoryManager\Config\GlobalConfigStorage;
use Puli\RepositoryManager\Config\Reader\GlobalConfigReaderInterface;
use Puli\RepositoryManager\Config\Writer\GlobalConfigWriterInterface;
use Puli\RepositoryManager\Config\GlobalEnvironment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestGlobalEnvironment extends GlobalEnvironment implements GlobalConfigReaderInterface, GlobalConfigWriterInterface
{
    private $globalConfig;

    public function __construct($homeDir, GlobalConfig $globalConfig, EventDispatcherInterface $dispatcher)
    {
        $this->globalConfig = $globalConfig;

        parent::__construct($homeDir, new GlobalConfigStorage($this, $this), $dispatcher);
    }

    public function readGlobalConfig($path)
    {
        return $this->globalConfig;
    }

    public function writeGlobalConfig(GlobalConfig $config, $path)
    {
    }
}
