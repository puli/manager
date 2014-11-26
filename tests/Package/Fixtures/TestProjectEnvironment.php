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
use Puli\RepositoryManager\Project\ProjectEnvironment;
use Puli\RepositoryManager\Package\Config\PackageConfig;
use Puli\RepositoryManager\Package\Config\PackageConfigStorage;
use Puli\RepositoryManager\Package\Config\Reader\PackageConfigReaderInterface;
use Puli\RepositoryManager\Package\Config\RootPackageConfig;
use Puli\RepositoryManager\Package\Config\Writer\PackageConfigWriterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestProjectEnvironment extends ProjectEnvironment implements GlobalConfigReaderInterface, GlobalConfigWriterInterface, PackageConfigReaderInterface, PackageConfigWriterInterface
{
    private $globalConfig;

    private $rootPackageConfig;

    public function __construct($homeDir, $rootDir, GlobalConfig $globalConfig, RootPackageConfig $rootPackageConfig, EventDispatcherInterface $dispatcher)
    {
        $this->globalConfig = $globalConfig;
        $this->rootPackageConfig = $rootPackageConfig;

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
        return $this->rootPackageConfig;
    }

    public function writePackageConfig(PackageConfig $config, $path)
    {
    }
}
