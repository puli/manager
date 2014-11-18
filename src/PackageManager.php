<?php

/*
 * This file is part of the Puli Packages package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Packages;

use Puli\Packages\Package\Config\Reader\PackageConfigReaderInterface;
use Puli\Packages\Package\Config\RootPackageConfig;
use Puli\Packages\Package\Package;
use Puli\Packages\Package\RootPackage;
use Puli\Packages\Repository\Config\Reader\RepositoryConfigReaderInterface;
use Puli\Packages\Repository\Config\RepositoryConfig;
use Puli\Packages\Repository\PackageRepository;
use Puli\Util\Path;

/**
 * Manages the package repository.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageManager
{
    /**
     * @var RepositoryConfigReaderInterface
     */
    private $repositoryConfigReader;

    /**
     * @var PackageConfigReaderInterface
     */
    private $packageConfigReader;

    /**
     * @var RootPackageConfig
     */
    private $rootPackageConfig;

    /**
     * @var RepositoryConfig
     */
    private $repositoryConfig;

    /**
     * @var PackageRepository
     */
    private $packageRepository;

    /**
     * Loads the repository at the given root directory.
     *
     * @param string                          $rootDirectory          The directory containing the root package.
     * @param RepositoryConfigReaderInterface $repositoryConfigReader The repository config file reader.
     * @param PackageConfigReaderInterface    $packageConfigReader    The package config file reader.
     */
    public function __construct($rootDirectory, RepositoryConfigReaderInterface $repositoryConfigReader, PackageConfigReaderInterface $packageConfigReader)
    {
        $this->packageRepository = new PackageRepository();
        $this->repositoryConfigReader = $repositoryConfigReader;
        $this->packageConfigReader = $packageConfigReader;
        $this->rootPackageConfig = $packageConfigReader->readRootPackageConfig($rootDirectory.'/puli.json');

        $this->packageRepository->addPackage(new RootPackage($this->rootPackageConfig, $rootDirectory));

        $repositoryConfig = $this->rootPackageConfig->getRepositoryConfig();
        $this->repositoryConfig = $repositoryConfigReader->readRepositoryConfig($rootDirectory.'/'.$repositoryConfig);

        foreach ($this->repositoryConfig->getPackageDefinitions() as $packageDefinition) {
            $installPath = Path::makeAbsolute($packageDefinition->getInstallPath(), $rootDirectory);
            $config = $this->packageConfigReader->readPackageConfig($installPath.'/puli.json');
            $package = new Package($config, $installPath);

            $this->packageRepository->addPackage($package);
        }
    }

    /**
     * @return PackageRepository
     */
    public function getPackageRepository()
    {
        return $this->packageRepository;
    }

    /**
     * @return RepositoryConfig
     */
    public function getRepositoryConfig()
    {
        return $this->repositoryConfig;
    }

    /**
     * @return RootPackageConfig
     */
    public function getRootPackageConfig()
    {
        return $this->rootPackageConfig;
    }
}
