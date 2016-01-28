<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Module;

use Exception;
use Puli\Manager\Assert\Assert;

/**
 * A configured module.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Module
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var ModuleFile
     */
    private $moduleFile;

    /**
     * @var string
     */
    private $installPath;

    /**
     * @var InstallInfo
     */
    private $installInfo;

    /**
     * @var int
     */
    private $state;

    /**
     * @var Exception[]
     */
    private $loadErrors;

    /**
     * Creates a new module.
     *
     * @param ModuleFile|null  $moduleFile  The module file or `null` if the
     *                                      module file could not be loaded.
     * @param string           $installPath The absolute install path.
     * @param InstallInfo|null $installInfo The install info of this module.
     * @param Exception[]      $loadErrors  The errors that happened during
     *                                      loading of the module, if any.
     */
    public function __construct(ModuleFile $moduleFile = null, $installPath, InstallInfo $installInfo = null, array $loadErrors = array())
    {
        Assert::absoluteSystemPath($installPath);
        Assert::allIsInstanceOf($loadErrors, 'Exception');

        // If a module name was set during installation, that name wins over
        // the predefined name in the puli.json file (if any)
        $this->name = $installInfo && null !== $installInfo->getModuleName()
            ? $installInfo->getModuleName()
            : ($moduleFile ? $moduleFile->getModuleName() : null);

        if (null === $this->name) {
            $this->name = $this->getDefaultName();
        }

        // The path is stored both here and in the install info. While the
        // install info contains the path as it is stored in the install file
        // (i.e. relative or absolute), the install path of the module is
        // always an absolute path.
        $this->installPath = $installPath;
        $this->installInfo = $installInfo;
        $this->moduleFile = $moduleFile;
        $this->loadErrors = $loadErrors;

        if (!file_exists($installPath)) {
            $this->state = ModuleState::NOT_FOUND;
        } elseif (count($loadErrors) > 0) {
            $this->state = ModuleState::NOT_LOADABLE;
        } else {
            $this->state = ModuleState::ENABLED;
        }
    }

    /**
     * Returns the name of the module.
     *
     * @return string The name of the module.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the absolute path at which the module is installed.
     *
     * @return string The absolute install path of the module.
     */
    public function getInstallPath()
    {
        return $this->installPath;
    }

    /**
     * Returns the module file of the module.
     *
     * @return ModuleFile|null The module file or `null` if the file could not
     *                         be loaded.
     */
    public function getModuleFile()
    {
        return $this->moduleFile;
    }

    /**
     * Returns the module's install info.
     *
     * @return InstallInfo The install info.
     */
    public function getInstallInfo()
    {
        return $this->installInfo;
    }

    /**
     * Returns the error that occurred during loading of the module.
     *
     * @return Exception[] The errors or an empty array if the module was
     *                     loaded successfully.
     */
    public function getLoadErrors()
    {
        return $this->loadErrors;
    }

    /**
     * Returns the state of the module.
     *
     * @return int One of the {@link ModuleState} constants.
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Returns whether the module is enabled.
     *
     * @return bool Returns `true` if the state is {@link ModuleState::ENABLED}.
     *
     * @see ModuleState::ENABLED
     */
    public function isEnabled()
    {
        return ModuleState::ENABLED === $this->state;
    }

    /**
     * Returns whether the module was not found.
     *
     * @return bool Returns `true` if the state is {@link ModuleState::NOT_FOUND}.
     *
     * @see ModuleState::NOT_FOUND
     */
    public function isNotFound()
    {
        return ModuleState::NOT_FOUND === $this->state;
    }

    /**
     * Returns whether the module was not loadable.
     *
     * @return bool Returns `true` if the state is {@link ModuleState::NOT_LOADABLE}.
     *
     * @see ModuleState::NOT_LOADABLE
     */
    public function isNotLoadable()
    {
        return ModuleState::NOT_LOADABLE === $this->state;
    }

    /**
     * Returns the default name of a module.
     */
    protected function getDefaultName()
    {
        return null;
    }
}
