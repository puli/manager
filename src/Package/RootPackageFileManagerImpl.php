<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package;

use Puli\RepositoryManager\Api\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Api\Package\RootPackageFile;
use Puli\RepositoryManager\Api\Package\RootPackageFileManager;
use Puli\RepositoryManager\Config\AbstractConfigFileManager;

/**
 * Manages changes to the root package file.
 *
 * Use this class to make persistent changes to the puli.json of a project.
 * Whenever you call methods in this class, the changes will be written to disk.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageFileManagerImpl extends AbstractConfigFileManager implements RootPackageFileManager
{
    /**
     * @var ProjectEnvironment
     */
    private $environment;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * Creates a new package file manager.
     *
     * @param ProjectEnvironment $environment        The project environment
     * @param PackageFileStorage $packageFileStorage The package file storage.
     */
    public function __construct(ProjectEnvironment $environment, PackageFileStorage $packageFileStorage)
    {
        $this->environment = $environment;
        $this->rootPackageFile = $environment->getRootPackageFile();
        $this->packageFileStorage = $packageFileStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * {@inheritdoc}
     */
    public function getPackageFile()
    {
        return $this->rootPackageFile;
    }

    /**
     * {@inheritdoc}
     */
    public function getPackageName()
    {
        return $this->rootPackageFile->getPackageName();
    }

    /**
     * {@inheritdoc}
     */
    public function setPackageName($packageName)
    {
        if ($packageName !== $this->rootPackageFile->getPackageName()) {
            $this->rootPackageFile->setPackageName($packageName);
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function installPluginClass($pluginClass)
    {
        if ($this->rootPackageFile->hasPluginClass($pluginClass)) {
            // Already installed locally
            return;
        }

        $this->rootPackageFile->addPluginClass($pluginClass);

        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
    }

    /**
     * {@inheritdoc}
     */
    public function isPluginClassInstalled($pluginClass, $includeGlobal = true)
    {
        return $this->rootPackageFile->hasPluginClass($pluginClass, $includeGlobal);
    }

    /**
     * {@inheritdoc}
     */
    public function getPluginClasses($includeGlobal = true)
    {
        return $this->rootPackageFile->getPluginClasses($includeGlobal);
    }

    /**
     * {@inheritdoc}
     */
    protected function getConfig()
    {
        return $this->rootPackageFile->getConfig();
    }

    /**
     * {@inheritdoc}
     */
    protected function saveConfigFile()
    {
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
    }
}
