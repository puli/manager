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

/**
 * The root module.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootModule extends Module
{
    /**
     * Creates a new root module.
     *
     * @param RootModuleFile $moduleFile  The module file.
     * @param string         $installPath The absolute install path.
     */
    public function __construct(RootModuleFile $moduleFile, $installPath)
    {
        parent::__construct($moduleFile, $installPath);
    }

    /**
     * Returns the module file of the module.
     *
     * @return RootModuleFile The module file.
     */
    public function getModuleFile()
    {
        return parent::getModuleFile();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultName()
    {
        return '__root__';
    }
}
