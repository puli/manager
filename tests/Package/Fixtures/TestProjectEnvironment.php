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

use Puli\Discovery\Api\EditableDiscovery;
use Puli\Repository\Api\EditableRepository;
use Puli\RepositoryManager\Api\Config\Config;
use Puli\RepositoryManager\Api\Config\ConfigFile;
use Puli\RepositoryManager\Api\Config\ConfigFileReader;
use Puli\RepositoryManager\Api\Config\ConfigFileWriter;
use Puli\RepositoryManager\Api\Package\PackageFile;
use Puli\RepositoryManager\Api\Package\PackageFileReader;
use Puli\RepositoryManager\Api\Package\PackageFileWriter;
use Puli\RepositoryManager\Api\Package\RootPackageFile;
use Puli\RepositoryManager\Config\ConfigFileStorage;
use Puli\RepositoryManager\Environment\ProjectEnvironmentImpl;
use Puli\RepositoryManager\Package\PackageFileStorage;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestProjectEnvironment extends ProjectEnvironmentImpl implements ConfigFileReader, ConfigFileWriter, PackageFileReader, PackageFileWriter
{
    private $configFile;

    private $rootPackageFile;

    private $repo;

    private $discovery;

    public function __construct($homeDir, $rootDir, ConfigFile $configFile, RootPackageFile $rootPackageFile, EventDispatcherInterface $dispatcher, EditableRepository $repo, EditableDiscovery $discovery)
    {
        $this->configFile = $configFile;
        $this->rootPackageFile = $rootPackageFile;
        $this->repo = $repo;
        $this->discovery = $discovery;

        parent::__construct($homeDir, $rootDir, new ConfigFileStorage($this, $this), new PackageFileStorage($this, $this, $dispatcher), $dispatcher);
    }

    public function getRepository()
    {
        return $this->repo;
    }

    public function getDiscovery()
    {
        return $this->discovery;
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
