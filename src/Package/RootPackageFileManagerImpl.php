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

use Exception;
use Puli\RepositoryManager\Api\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Api\Factory\FactoryManager;
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
     * @var FactoryManager
     */
    private $factoryManager;

    /**
     * Creates a new package file manager.
     *
     * @param ProjectEnvironment $environment        The project environment
     * @param PackageFileStorage $packageFileStorage The package file storage.
     * @param FactoryManager     $factoryManager     The manager used to regenerate
     *                                               the Puli factory class after
     *                                               changing the configuration.
     */
    public function __construct(ProjectEnvironment $environment, PackageFileStorage $packageFileStorage, FactoryManager $factoryManager)
    {
        parent::__construct($factoryManager);

        $this->environment = $environment;
        $this->rootPackageFile = $environment->getRootPackageFile();
        $this->packageFileStorage = $packageFileStorage;
        $this->factoryManager = $factoryManager;
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
        if ($packageName === $this->rootPackageFile->getPackageName()) {
            return;
        }

        $this->rootPackageFile->setPackageName($packageName);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        $this->factoryManager->autoGenerateFactoryClass();
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
        $this->factoryManager->autoGenerateFactoryClass();
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
    public function setExtraKey($key, $value)
    {
        $previouslySet = $this->rootPackageFile->hasExtraKey($key);
        $previousValue = $this->rootPackageFile->getExtraKey($key);

        if ($value === $previousValue) {
            return;
        }

        $this->rootPackageFile->setExtraKey($key, $value);

        try {
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            if ($previouslySet) {
                $this->rootPackageFile->setExtraKey($key, $previousValue);
            } else {
                $this->rootPackageFile->removeExtraKey($key);
            }

            throw $e;
        }

        $this->factoryManager->autoGenerateFactoryClass();
    }

    /**
     * {@inheritdoc}
     */
    public function setExtraKeys(array $values)
    {
        $previousValues = array();
        $previouslyUnset = array();

        foreach ($values as $key => $value) {
            if ($this->rootPackageFile->hasExtraKey($key)) {
                if ($value !== $previous = $this->rootPackageFile->getExtraKey($key)) {
                    $previousValues[$key] = $previous;
                }
            } else {
                $previouslyUnset[$key] = true;
            }
        }

        if (!$previousValues && !$previouslyUnset) {
            return;
        }

        $this->rootPackageFile->setExtraKeys($values);

        try {
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            foreach ($values as $key => $value) {
                if (isset($previouslyUnset[$key])) {
                    $this->rootPackageFile->removeExtraKey($key);
                } else {
                    $this->rootPackageFile->setExtraKey($key, $previousValues[$key]);
                }
            }

            throw $e;
        }

        $this->factoryManager->autoGenerateFactoryClass();
    }

    /**
     * {@inheritdoc}
     */
    public function removeExtraKey($key)
    {
        if (!$this->rootPackageFile->hasExtraKey($key)) {
            return;
        }

        $previousValue = $this->rootPackageFile->getExtraKey($key);

        $this->rootPackageFile->removeExtraKey($key);

        try {
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            $this->rootPackageFile->setExtraKey($key, $previousValue);

            throw $e;
        }

        $this->factoryManager->autoGenerateFactoryClass();
    }

    /**
     * {@inheritdoc}
     */
    public function removeExtraKeys(array $keys)
    {
        $previousValues = array();

        foreach ($keys as $key) {
            if ($this->rootPackageFile->hasExtraKey($key)) {
                $previousValues[$key] = $this->rootPackageFile->getExtraKey($key);
            }

            $this->rootPackageFile->removeExtraKey($key);
        }

        if (!$previousValues) {
            return;
        }

        try {
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            $this->rootPackageFile->addExtraKeys($previousValues);

            throw $e;
        }

        $this->factoryManager->autoGenerateFactoryClass();
    }

    /**
     * {@inheritdoc}
     */
    public function clearExtraKeys()
    {
        $previousValues = $this->rootPackageFile->getExtraKeys();

        if (!$previousValues) {
            return;
        }

        $this->rootPackageFile->clearExtraKeys();

        try {
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            $this->rootPackageFile->setExtraKeys($previousValues);

            throw $e;
        }

        $this->factoryManager->autoGenerateFactoryClass();
    }

    /**
     * {@inheritdoc}
     */
    public function hasExtraKey($key)
    {
        return $this->rootPackageFile->hasExtraKey($key);
    }

    /**
     * {@inheritdoc}
     */
    public function hasExtraKeys()
    {
        return $this->rootPackageFile->hasExtraKeys();
    }

    /**
     * {@inheritdoc}
     */
    public function getExtraKey($key, $default = null)
    {
        return $this->rootPackageFile->getExtraKey($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function getExtraKeys()
    {
        return $this->rootPackageFile->getExtraKeys();
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
