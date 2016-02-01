<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Module;

use Exception;
use InvalidArgumentException;
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Api\Module\RootModuleFileManager;
use Puli\Manager\Config\AbstractConfigManager;
use Puli\Manager\Json\JsonStorage;
use ReflectionClass;
use ReflectionException;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;

/**
 * Manages changes to the root module file.
 *
 * Use this class to make persistent changes to the puli.json of a project.
 * Whenever you call methods in this class, the changes will be written to disk.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootModuleFileManagerImpl extends AbstractConfigManager implements RootModuleFileManager
{
    /**
     * @var ProjectContext
     */
    private $context;

    /**
     * @var RootModuleFile
     */
    private $rootModuleFile;

    /**
     * @var JsonStorage
     */
    private $jsonStorage;

    /**
     * Creates a new module file manager.
     *
     * @param ProjectContext $context     The project context
     * @param JsonStorage    $jsonStorage The module file storage.
     */
    public function __construct(ProjectContext $context, JsonStorage $jsonStorage)
    {
        $this->context = $context;
        $this->rootModuleFile = $context->getRootModuleFile();
        $this->jsonStorage = $jsonStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return $this->rootModuleFile->getConfig();
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function getModuleFile()
    {
        return $this->rootModuleFile;
    }

    /**
     * {@inheritdoc}
     */
    public function getModuleName()
    {
        return $this->rootModuleFile->getModuleName();
    }

    /**
     * {@inheritdoc}
     */
    public function setModuleName($moduleName)
    {
        if ($moduleName === $this->rootModuleFile->getModuleName()) {
            return;
        }

        $previousName = $this->rootModuleFile->getModuleName();

        $this->rootModuleFile->setModuleName($moduleName);

        try {
            $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
        } catch (Exception $e) {
            $this->rootModuleFile->setModuleName($previousName);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addPluginClass($pluginClass)
    {
        if ($this->rootModuleFile->hasPluginClass($pluginClass)) {
            // Already installed locally
            return;
        }

        $this->validatePluginClass($pluginClass);

        $previousClasses = $this->rootModuleFile->getPluginClasses();

        $this->rootModuleFile->addPluginClass($pluginClass);

        try {
            $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
        } catch (Exception $e) {
            $this->rootModuleFile->setPluginClasses($previousClasses);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removePluginClass($pluginClass)
    {
        if (!$this->rootModuleFile->hasPluginClass($pluginClass)) {
            return;
        }

        $previousClasses = $this->rootModuleFile->getPluginClasses();

        $this->rootModuleFile->removePluginClass($pluginClass);

        try {
            $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
        } catch (Exception $e) {
            $this->rootModuleFile->setPluginClasses($previousClasses);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removePluginClasses(Expression $expr)
    {
        $save = false;
        $previousClasses = $this->rootModuleFile->getPluginClasses();

        foreach ($previousClasses as $pluginClass) {
            if ($expr->evaluate($pluginClass)) {
                $this->rootModuleFile->removePluginClass($pluginClass);
                $save = true;
            }
        }

        if (!$save) {
            return;
        }

        try {
            $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
        } catch (Exception $e) {
            $this->rootModuleFile->setPluginClasses($previousClasses);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearPluginClasses()
    {
        $this->removePluginClasses(Expr::true());
    }

    /**
     * {@inheritdoc}
     */
    public function hasPluginClass($pluginClass)
    {
        return $this->rootModuleFile->hasPluginClass($pluginClass);
    }

    /**
     * {@inheritdoc}
     */
    public function hasPluginClasses(Expression $expr = null)
    {
        if (!$expr) {
            return $this->rootModuleFile->hasPluginClasses();
        }

        foreach ($this->rootModuleFile->getPluginClasses() as $pluginClass) {
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
        return $this->rootModuleFile->getPluginClasses();
    }

    /**
     * {@inheritdoc}
     */
    public function findPluginClasses(Expression $expr)
    {
        $pluginClasses = array();

        foreach ($this->rootModuleFile->getPluginClasses() as $pluginClass) {
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
        $previouslySet = $this->rootModuleFile->hasExtraKey($key);
        $previousValue = $this->rootModuleFile->getExtraKey($key);

        if ($value === $previousValue) {
            return;
        }

        $this->rootModuleFile->setExtraKey($key, $value);

        try {
            $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
        } catch (Exception $e) {
            if ($previouslySet) {
                $this->rootModuleFile->setExtraKey($key, $previousValue);
            } else {
                $this->rootModuleFile->removeExtraKey($key);
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
            if ($this->rootModuleFile->hasExtraKey($key)) {
                if ($value !== $previous = $this->rootModuleFile->getExtraKey($key)) {
                    $previousValues[$key] = $previous;
                }
            } else {
                $previouslyUnset[$key] = true;
            }
        }

        if (!$previousValues && !$previouslyUnset) {
            return;
        }

        $this->rootModuleFile->setExtraKeys($values);

        try {
            $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
        } catch (Exception $e) {
            foreach ($values as $key => $value) {
                if (isset($previouslyUnset[$key])) {
                    $this->rootModuleFile->removeExtraKey($key);
                } else {
                    $this->rootModuleFile->setExtraKey($key, $previousValues[$key]);
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
        if (!$this->rootModuleFile->hasExtraKey($key)) {
            return;
        }

        $previousValue = $this->rootModuleFile->getExtraKey($key);

        $this->rootModuleFile->removeExtraKey($key);

        try {
            $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
        } catch (Exception $e) {
            $this->rootModuleFile->setExtraKey($key, $previousValue);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeExtraKeys(Expression $expr)
    {
        $previousValues = $this->rootModuleFile->getExtraKeys();
        $save = false;

        foreach ($this->rootModuleFile->getExtraKeys() as $key => $value) {
            if ($expr->evaluate($key)) {
                $this->rootModuleFile->removeExtraKey($key);
                $save = true;
            }
        }

        if (!$save) {
            return;
        }

        try {
            $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
        } catch (Exception $e) {
            $this->rootModuleFile->setExtraKeys($previousValues);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearExtraKeys()
    {
        $previousValues = $this->rootModuleFile->getExtraKeys();

        if (!$previousValues) {
            return;
        }

        $this->rootModuleFile->clearExtraKeys();

        try {
            $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
        } catch (Exception $e) {
            $this->rootModuleFile->setExtraKeys($previousValues);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasExtraKey($key)
    {
        return $this->rootModuleFile->hasExtraKey($key);
    }

    /**
     * {@inheritdoc}
     */
    public function hasExtraKeys(Expression $expr = null)
    {
        if (!$expr) {
            return $this->rootModuleFile->hasExtraKeys();
        }

        foreach ($this->rootModuleFile->getExtraKeys() as $key => $value) {
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
        return $this->rootModuleFile->getExtraKey($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function getExtraKeys()
    {
        return $this->rootModuleFile->getExtraKeys();
    }

    /**
     * {@inheritdoc}
     */
    public function findExtraKeys(Expression $expr)
    {
        $values = array();

        foreach ($this->rootModuleFile->getExtraKeys() as $key => $value) {
            if ($expr->evaluate($key)) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function migrate($targetVersion)
    {
        $previousVersion = $this->rootModuleFile->getVersion();

        if ($previousVersion === $targetVersion) {
            return;
        }

        $this->rootModuleFile->setVersion($targetVersion);

        try {
            $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
        } catch (Exception $e) {
            $this->rootModuleFile->setVersion($previousVersion);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function saveConfigFile()
    {
        $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
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
