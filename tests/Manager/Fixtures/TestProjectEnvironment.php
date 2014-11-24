<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Manager\Fixtures;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Config\GlobalConfigStorage;
use Puli\PackageManager\Config\Reader\GlobalConfigReaderInterface;
use Puli\PackageManager\Config\Writer\GlobalConfigWriterInterface;
use Puli\PackageManager\Manager\ProjectEnvironment;
use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\PackageConfigStorage;
use Puli\PackageManager\Package\Config\Reader\PackageConfigReaderInterface;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\Config\Writer\PackageConfigWriterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestProjectEnvironment extends ProjectEnvironment implements GlobalConfigReaderInterface, GlobalConfigWriterInterface, PackageConfigReaderInterface, PackageConfigWriterInterface
{
    private $globalConfig;

    private $projectConfig;

    public function __construct($homeDir, $rootDir, GlobalConfig $globalConfig, RootPackageConfig $rootPackageConfig, EventDispatcherInterface $dispatcher)
    {
        $this->globalConfig = $globalConfig;
        $this->projectConfig = $rootPackageConfig;

        parent::__construct($homeDir, $rootDir, new GlobalConfigStorage($this, $this), new PackageConfigStorage($this, $this, $dispatcher), $dispatcher);
    }

    public function readGlobalConfig($path)
    {
        return $this->globalConfig;
    }

    public function writeGlobalConfig(GlobalConfig $config, $path)
    {
    }

    public function readPackageConfig($path)
    {
    }

    public function readRootPackageConfig($path, GlobalConfig $globalConfig)
    {
        return $this->projectConfig;
    }

    public function writePackageConfig(PackageConfig $config, $path)
    {
    }
}
