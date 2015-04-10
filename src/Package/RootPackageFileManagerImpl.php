<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Package;

use Exception;
use InvalidArgumentException;
use Puli\Manager\Api\Environment\ProjectEnvironment;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Package\RootPackageFileManager;
use Puli\Manager\Config\AbstractConfigFileManager;
use ReflectionClass;
use ReflectionException;
use Webmozart\Expression\Expression;

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
        if ($packageName === $this->rootPackageFile->getPackageName()) {
            return;
        }

        $previousName = $this->rootPackageFile->getPackageName();

        $this->rootPackageFile->setPackageName($packageName);

        try {
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            $this->rootPackageFile->setPackageName($previousName);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addPluginClass($pluginClass)
    {
        if ($this->rootPackageFile->hasPluginClass($pluginClass)) {
            // Already installed locally
            return;
        }

        $this->validatePluginClass($pluginClass);

        $previousClasses = $this->rootPackageFile->getPluginClasses();

        $this->rootPackageFile->addPluginClass($pluginClass);

        try {
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            $this->rootPackageFile->setPluginClasses($previousClasses);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removePluginClass($pluginClass)
    {
        if (!$this->rootPackageFile->hasPluginClass($pluginClass)) {
            return;
        }

        $previousClasses = $this->rootPackageFile->getPluginClasses();

        $this->rootPackageFile->removePluginClass($pluginClass);

        try {
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            $this->rootPackageFile->setPluginClasses($previousClasses);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removePluginClasses(Expression $expr)
    {
        $save = false;
        $previousClasses = $this->rootPackageFile->getPluginClasses();

        foreach ($previousClasses as $pluginClass) {
            if ($expr->evaluate($pluginClass)) {
                $this->rootPackageFile->removePluginClass($pluginClass);
                $save = true;
            }
        }

        if (!$save) {
            return;
        }

        try {
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            $this->rootPackageFile->setPluginClasses($previousClasses);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearPluginClasses()
    {
        if (!$this->rootPackageFile->hasPluginClasses()) {
            return;
        }

        $previousClasses = $this->rootPackageFile->getPluginClasses();

        $this->rootPackageFile->clearPluginClasses();

        try {
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            $this->rootPackageFile->setPluginClasses($previousClasses);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasPluginClass($pluginClass)
    {
        return $this->rootPackageFile->hasPluginClass($pluginClass);
    }

    /**
     * {@inheritdoc}
     */
    public function hasPluginClasses(Expression $expr = null)
    {
        if (!$expr) {
            return $this->rootPackageFile->hasPluginClasses();
        }

        foreach ($this->rootPackageFile->getPluginClasses() as $pluginClass) {
            if ($expr->evaluate($pluginClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getPluginClasses()
    {
        return $this->rootPackageFile->getPluginClasses();
    }

    /**
     * {@inheritdoc}
     */
    public function findPluginClasses(Expression $expr)
    {
        $pluginClasses = array();

        foreach ($this->rootPackageFile->getPluginClasses() as $pluginClass) {
            if ($expr->evaluate($pluginClass)) {
                $pluginClasses[] = $pluginClass;
            }
        }

        return $pluginClasses;
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
    }

    /**
     * {@inheritdoc}
     */
    public function removeExtraKeys(Expression $expr)
    {
        $previousValues = $this->rootPackageFile->getExtraKeys();
        $save = false;

        foreach ($this->rootPackageFile->getExtraKeys() as $key => $value) {
            if ($expr->evaluate($key)) {
                $this->rootPackageFile->removeExtraKey($key);
                $save = true;
            }
        }

        if (!$save) {
            return;
        }

        try {
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            $this->rootPackageFile->setExtraKeys($previousValues);

            throw $e;
        }
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
    public function hasExtraKeys(Expression $expr = null)
    {
        if (!$expr) {
            return $this->rootPackageFile->hasExtraKeys();
        }

        foreach ($this->rootPackageFile->getExtraKeys() as $key => $value) {
            if ($expr->evaluate($key)) {
                return true;
            }
        }

        return false;
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
    public function findExtraKeys(Expression $expr)
    {
        $values = array();

        foreach ($this->rootPackageFile->getExtraKeys() as $key => $value) {
            if ($expr->evaluate($key)) {
                $values[$key] = $value;
            }
        }

        return $values;
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

    private function validatePluginClass($pluginClass)
    {
        try {
            $reflClass = new ReflectionClass($pluginClass);
        } catch (ReflectionException $e) {
            throw new InvalidArgumentException(sprintf(
                'The plugin class %s does not exist.',
                $pluginClass
            ), 0, $e);
        }

        if ($reflClass->isInterface()) {
            throw new InvalidArgumentException(sprintf(
                'The plugin class %s should be a class, but is an interface.',
                $pluginClass
            ));
        }

        if (version_compare(PHP_VERSION, '5.4.0', '>=') && $reflClass->isTrait()) {
            throw new InvalidArgumentException(sprintf(
                'The plugin class %s should be a class, but is a trait.',
                $pluginClass
            ));
        }

        if (!$reflClass->implementsInterface('\Puli\Manager\Api\PuliPlugin')) {
            throw new InvalidArgumentException(sprintf(
                'The plugin class %s must implement PuliPlugin.',
                $pluginClass
            ));
        }

        $constructor = $reflClass->getConstructor();

        if (null !== $constructor && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new InvalidArgumentException(sprintf(
                'The constructor of the plugin class %s must not have required '.
                'parameters.',
                $pluginClass
            ));
        }
    }
}
