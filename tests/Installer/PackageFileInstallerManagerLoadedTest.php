<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Installer;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileInstallerManagerLoadedTest extends PackageFileInstallerManagerUnloadedTest
{
    protected function populateDefaultManager()
    {
        parent::populateDefaultManager();

        // Load descriptors
        $this->manager->getInstallerDescriptors();
    }
}
