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
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\Reader\PackageFileReader;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\PackageFile\Writer\PackageFileWriter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestProjectEnvironment extends ProjectEnvironment implements ConfigFileReader, ConfigFileWriter, PackageFileReader, PackageFileWriter
{
    private $configFile;

    private $rootPackageFile;

    public function __construct($homeDir, $rootDir, ConfigFile $configFile, RootPackageFile $rootPackageFile, EventDispatcherInterface $dispatcher)
    {
        $this->configFile = $configFile;
        $this->rootPackageFile = $rootPackageFile;

        parent::__construct($homeDir, $rootDir, new ConfigFileStorage($this, $this), new PackageFileStorage($this, $this, $dispatcher), $dispatcher);
    }

    public function readConfigFile($path, Config $baseConfig = null)
    {
        return $this->configFile;
    }

    public function writeConfigFile(ConfigFile $configFile, $path)
    {
    }

    public function readPackageFile($path)
    {
    }

    public function readRootPackageFile($path, Config $baseConfig = null)
    {
        return $this->rootPackageFile;
    }

    public function writePackageFile(PackageFile $packageFile, $path)
    {
    }
}
