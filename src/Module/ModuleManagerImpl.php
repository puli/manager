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
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\InvalidConfigException;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\ModuleList;
use Puli\Manager\Api\Module\ModuleManager;
use Puli\Manager\Api\Module\NameConflictException;
use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Api\Module\UnsupportedVersionException;
use Puli\Manager\Api\NoDirectoryException;
use Puli\Manager\Assert\Assert;
use Puli\Manager\Json\JsonStorage;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;
use Webmozart\PathUtil\Path;

/**
 * Manages the module repository of a Puli project.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleManagerImpl implements ModuleManager
{
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
     * @var JsonStorage
     */
    private $jsonStorage;

    /**
     * @var ModuleList
     */
    private $modules;

    /**
     * Loads the module repository for a given project.
     *
     * @param ProjectContext $context     The project context.
     * @param JsonStorage    $jsonStorage The module file storage.
     *
     * @throws FileNotFoundException  If the install path of a module not exist.
     * @throws NoDirectoryException   If the install path of a module points to a file.
     * @throws InvalidConfigException If a configuration file contains invalid configuration.
     * @throws NameConflictException  If a module has the same name as another loaded module.
     */
    public function __construct(ProjectContext $context, JsonStorage $jsonStorage)
    {
        $this->context = $context;
        $this->rootDir = $context->getRootDirectory();
        $this->rootModuleFile = $context->getRootModuleFile();
        $this->jsonStorage = $jsonStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function installModule($installPath, $name = null, $installerName = InstallInfo::DEFAULT_INSTALLER_NAME, $env = Environment::PROD)
    {
        Assert::string($installPath, 'The install path must be a string. Got: %s');
        Assert::string($installerName, 'The installer name must be a string. Got: %s');
        Assert::oneOf($env, Environment::all(), 'The environment must be one of: %2$s. Got: %s');
        Assert::nullOrModuleName($name);

        $this->assertModulesLoaded();

        $installPath = Path::makeAbsolute($installPath, $this->rootDir);

        foreach ($this->modules as $module) {
            if ($installPath === $module->getInstallPath()) {
                return;
            }
        }

        if (null === $name && $moduleFile = $this->loadModuleFile($installPath)) {
            // Read the name from the module file
            $name = $moduleFile->getModuleName();
        }

        if (null === $name) {
            throw new InvalidConfigException(sprintf(
                'Could not find a name for the module at %s. The name should '.
                'either be passed to the installer or be set in the "name" '.
                'property of %s.',
                $installPath,
                $installPath.'/puli.json'
            ));
        }

        if ($this->modules->contains($name)) {
            throw NameConflictException::forName($name);
        }

        $relInstallPath = Path::makeRelative($installPath, $this->rootDir);
        $installInfo = new InstallInfo($name, $relInstallPath);
        $installInfo->setInstallerName($installerName);
        $installInfo->setEnvironment($env);

        $module = $this->loadModule($installInfo);

        $this->assertNoLoadErrors($module);
        $this->rootModuleFile->addInstallInfo($installInfo);

        try {
            $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
        } catch (Exception $e) {
            $this->rootModuleFile->removeInstallInfo($name);

            throw $e;
        }

        $this->modules->add($module);
    }

    /**
     * {@inheritdoc}
     */
    public function renameModule($name, $newName)
    {
        $module = $this->getModule($name);

        if ($name === $newName) {
            return;
        }

        if ($this->modules->contains($newName)) {
            throw NameConflictException::forName($newName);
        }

        if ($module instanceof RootModule) {
            $this->renameRootModule($module, $newName);
        } else {
            $this->renameNonRootModule($module, $newName);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeModule($name)
    {
        // Only check that this is a string. The error message "not found" is
        // more helpful than e.g. "module name must contain /".
        Assert::string($name, 'The module name must be a string. Got: %s');

        $this->assertModulesLoaded();

        if ($this->rootModuleFile->hasInstallInfo($name)) {
            $installInfo = $this->rootModuleFile->getInstallInfo($name);
            $this->rootModuleFile->removeInstallInfo($name);

            try {
                $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
            } catch (Exception $e) {
                $this->rootModuleFile->addInstallInfo($installInfo);

                throw $e;
            }
        }

        $this->modules->remove($name);
    }

    /**
     * {@inheritdoc}
     */
    public function removeModules(Expression $expr)
    {
        $this->assertModulesLoaded();

        $installInfos = $this->rootModuleFile->getInstallInfos();
        $modules = $this->modules->toArray();

        foreach ($this->modules->getInstalledModules() as $module) {
            if ($expr->evaluate($module)) {
                $this->rootModuleFile->removeInstallInfo($module->getName());
                $this->modules->remove($module->getName());
            }
        }

        if (!$installInfos) {
            return;
        }

        try {
            $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
        } catch (Exception $e) {
            $this->rootModuleFile->setInstallInfos($installInfos);
            $this->modules->replace($modules);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearModules()
    {
        $this->removeModules(Expr::true());
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

        // Never return he original collection
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

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Loads all modules referenced by the install file.
     *
     * @throws FileNotFoundException  If the install path of a module not exist.
     * @throws NoDirectoryException   If the install path of a module points to a
     *                                file.
     * @throws InvalidConfigException If a module is not configured correctly.
     * @throws NameConflictException  If a module has the same name as another
     *                                loaded module.
     */
    private function loadModules()
    {
        $this->modules = new ModuleList();
        $this->modules->add(new RootModule($this->rootModuleFile, $this->rootDir));

        foreach ($this->rootModuleFile->getInstallInfos() as $installInfo) {
            $this->modules->add($this->loadModule($installInfo));
        }
    }

    /**
     * Loads a module for the given install info.
     *
     * @param InstallInfo $installInfo The install info.
     *
     * @return Module The module.
     */
    private function loadModule(InstallInfo $installInfo)
    {
        $installPath = Path::makeAbsolute($installInfo->getInstallPath(), $this->rootDir);
        $moduleFile = null;
        $loadError = null;

        // Catch and log exceptions so that single modules cannot break
        // the whole repository
        try {
            $moduleFile = $this->loadModuleFile($installPath);
        } catch (InvalidConfigException $loadError) {
        } catch (UnsupportedVersionException $loadError) {
        } catch (FileNotFoundException $loadError) {
        } catch (NoDirectoryException $loadError) {
        }

        $loadErrors = $loadError ? array($loadError) : array();

        return new Module($moduleFile, $installPath, $installInfo, $loadErrors);
    }

    /**
     * Loads the module file for the module at the given install path.
     *
     * @param string $installPath The absolute install path of the module
     *
     * @return ModuleFile|null The loaded module file or `null` if none
     *                         could be found.
     */
    private function loadModuleFile($installPath)
    {
        if (!file_exists($installPath)) {
            throw FileNotFoundException::forPath($installPath);
        }

        if (!is_dir($installPath)) {
            throw new NoDirectoryException(sprintf(
                'The path %s is a file. Expected a directory.',
                $installPath
            ));
        }

        try {
            return $this->jsonStorage->loadModuleFile($installPath.'/puli.json');
        } catch (FileNotFoundException $e) {
            // Modules without module files are ok
            return null;
        }
    }

    private function assertModulesLoaded()
    {
        if (!$this->modules) {
            $this->loadModules();
        }
    }

    private function assertNoLoadErrors(Module $module)
    {
        $loadErrors = $module->getLoadErrors();

        if (count($loadErrors) > 0) {
            // Rethrow first error
            throw reset($loadErrors);
        }
    }

    private function renameRootModule(RootModule $module, $newName)
    {
        $moduleFile = $module->getModuleFile();
        $previousName = $moduleFile->getModuleName();
        $moduleFile->setModuleName($newName);

        try {
            $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
        } catch (Exception $e) {
            $moduleFile->setModuleName($previousName);

            throw $e;
        }

        $this->modules->remove($module->getName());
        $this->modules->add(new RootModule($moduleFile, $module->getInstallPath()));
    }

    private function renameNonRootModule(Module $module, $newName)
    {
        $previousInstallInfo = $module->getInstallInfo();

        $installInfo = new InstallInfo($newName, $previousInstallInfo->getInstallPath());
        $installInfo->setInstallerName($previousInstallInfo->getInstallerName());

        foreach ($previousInstallInfo->getDisabledBindingUuids() as $uuid) {
            $installInfo->addDisabledBindingUuid($uuid);
        }

        $this->rootModuleFile->removeInstallInfo($module->getName());
        $this->rootModuleFile->addInstallInfo($installInfo);

        try {
            $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
        } catch (Exception $e) {
            $this->rootModuleFile->removeInstallInfo($newName);
            $this->rootModuleFile->addInstallInfo($previousInstallInfo);

            throw $e;
        }

        $this->modules->remove($module->getName());
        $this->modules->add(new Module(
            $module->getModuleFile(),
            $module->getInstallPath(),
            $installInfo,
            $module->getLoadErrors()
        ));
    }
}
