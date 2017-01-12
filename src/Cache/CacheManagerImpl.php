<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Cache;

use Puli\Manager\Api\Cache\CacheFile;
use Puli\Manager\Api\Cache\CacheManager;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\ModuleList;
use Puli\Manager\Api\Module\ModuleManager;
use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Assert\Assert;
use Puli\Manager\Json\JsonStorage;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;
use Webmozart\PathUtil\Path;

/**
 * Manages cached Modules information.
 *
 * @since  1.0
 *
 * @author Mateusz Sojda <mateusz@sojda.pl>
 */
class CacheManagerImpl implements CacheManager
{
    /**
     * @var CacheFile
     */
    private $cacheFile;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var JsonStorage
     */
    private $storage;

    /**
     * @var ProjectContext
     */
    private $context;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var RootModuleFile
     */
    private $rootModuleFile;

    /**
     * @var ModuleList
     */
    private $modules;

    /**
     * Creates new cache manager.
     *
     * @param ModuleManager  $moduleManager The module manager.
     * @param JsonStorage    $storage       The file storage.
     * @param ProjectContext $context       Project context.
     */
    public function __construct(ModuleManager $moduleManager, JsonStorage $storage, ProjectContext $context)
    {
        $this->moduleManager = $moduleManager;
        $this->storage = $storage;
        $this->context = $context;
        $this->rootDir = $context->getRootDirectory();
        $this->rootModuleFile = $context->getRootModuleFile();
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
    public function getCacheFile()
    {
        $path = $this->getContext()->getConfig()->get(Config::CACHE_FILE);
        $path = Path::makeAbsolute($path, $this->rootDir);

        if (!$this->storage->fileExists($path)) {
            $this->refreshCacheFile();
        }

        if (false === $this->cacheFile instanceof CacheFile) {
            $this->cacheFile = $this->storage->loadCacheFile($path);
        }

        return $this->cacheFile;
    }

    /**
     * {@inheritdoc}
     */
    public function refreshCacheFile()
    {
        $path = $this->getContext()->getConfig()->get(Config::CACHE_FILE);
        $path = Path::makeAbsolute($path, $this->rootDir);

        if ($this->storage->fileExists($path) && !$this->isRootModuleFileModified()) {
            return;
        }

        $cacheFile = new CacheFile(Path::makeAbsolute($path, $this->rootDir));

        foreach ($this->getInstalledModules() as $module) {
            $moduleFile = $module->getModuleFile();
            if (!$moduleFile instanceof ModuleFile) {
                continue;
            }

            $cacheFile->addModuleFile($moduleFile);

            $installInfo = $module->getInstallInfo();
            $cacheFile->addInstallInfo($installInfo);
        }

        $this->storage->saveCacheFile($cacheFile);

        $this->cacheFile = null;
    }

    /**
     * {@inheritdoc}
     */
    public function getModule($name)
    {
        Assert::string($name, 'The module name must be a string. Got: %s');

        $this->assertModulesLoaded();

        return $this->modules->get($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getRootModule()
    {
        $this->assertModulesLoaded();

        return $this->modules->getRootModule();
    }

    /**
     * {@inheritdoc}
     */
    public function getModules()
    {
        $this->assertModulesLoaded();

        return clone $this->modules;
    }

    /**
     * {@inheritdoc}
     */
    public function findModules(Expression $expr)
    {
        $this->assertModulesLoaded();

        $modules = new ModuleList();

        foreach ($this->modules as $module) {
            if ($expr->evaluate($module)) {
                $modules->add($module);
            }
        }

        return $modules;
    }

    /**
     * {@inheritdoc}
     */
    public function hasModule($name)
    {
        Assert::string($name, 'The module name must be a string. Got: %s');

        $this->assertModulesLoaded();

        return $this->modules->contains($name);
    }

    /**
     * {@inheritdoc}
     */
    public function hasModules(Expression $expr = null)
    {
        $this->assertModulesLoaded();

        if (!$expr) {
            return !$this->modules->isEmpty();
        }

        foreach ($this->modules as $module) {
            if ($expr->evaluate($module)) {
                return true;
            }
        }

        return false;
    }

    private function assertModulesLoaded()
    {
        if (!$this->modules) {
            $this->loadModules();
        }
    }

    private function loadModules()
    {
        $cacheFile = $this->getCacheFile();

        $this->modules = new ModuleList();
        $this->modules->add(new RootModule($this->rootModuleFile, $this->rootDir));

        foreach ($cacheFile->getModuleFiles() as $moduleFile) {
            $this->modules->add($this->buildModule($moduleFile, $cacheFile));
        }
    }

    private function getInstalledModules()
    {
        $modules = $this->moduleManager->findModules(Expr::true());

        return $modules->getInstalledModules();
    }

    private function buildModule(ModuleFile $moduleFile, CacheFile $cacheFile)
    {
        $installInfo = $cacheFile->getInstallInfo($moduleFile->getModuleName());
        $installPath = Path::makeAbsolute($installInfo->getInstallPath(), $this->rootDir);

        return new Module($moduleFile, $installPath, $installInfo);
    }

    private function isRootModuleFileModified()
    {
        $cacheFilePath = $this->getContext()->getConfig()->get(Config::CACHE_FILE);
        $cacheFilePath = Path::makeAbsolute($cacheFilePath, $this->rootDir);

        clearstatcache(true, $cacheFilePath);
        $cacheFileMtime = filemtime($cacheFilePath);

        $rootModuleFilePath = $this->rootModuleFile->getPath();

        if (false === $this->storage->fileExists($rootModuleFilePath)) {
            return true;
        }

        clearstatcache(true, $rootModuleFilePath);
        $rootModuleFileMtime = filemtime($rootModuleFilePath);

        if ($rootModuleFileMtime > $cacheFileMtime) {
            return true;
        }

        return false;
    }
}
