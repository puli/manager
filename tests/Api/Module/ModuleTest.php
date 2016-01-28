<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Module;

use Exception;
use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\ModuleState;
use RuntimeException;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleTest extends PHPUnit_Framework_TestCase
{
    public function testUseModuleNameFromModuleFile()
    {
        $moduleFile = new ModuleFile('vendor/name');
        $module = new Module($moduleFile, '/path');

        $this->assertSame('vendor/name', $module->getName());
    }

    public function testUseModuleNameFromInstallInfo()
    {
        $moduleFile = new ModuleFile();
        $installInfo = new InstallInfo('vendor/name', '/path');
        $module = new Module($moduleFile, '/path', $installInfo);

        $this->assertSame('vendor/name', $module->getName());
    }

    public function testPreferModuleNameFromInstallInfo()
    {
        $moduleFile = new ModuleFile('vendor/module-file');
        $installInfo = new InstallInfo('vendor/install-info', '/path');
        $module = new Module($moduleFile, '/path', $installInfo);

        $this->assertSame('vendor/install-info', $module->getName());
    }

    public function testNameIsNullIfNoneSetAndNoInstallInfoGiven()
    {
        $moduleFile = new ModuleFile();
        $module = new Module($moduleFile, '/path');

        $this->assertNull($module->getName());
    }

    public function testEnabledIfFound()
    {
        $moduleFile = new ModuleFile('vendor/name');
        $module = new Module($moduleFile, __DIR__);

        $this->assertSame(ModuleState::ENABLED, $module->getState());
    }

    public function testNotFoundIfNotFound()
    {
        $moduleFile = new ModuleFile('vendor/name');
        $module = new Module($moduleFile, __DIR__.'/foobar');

        $this->assertSame(ModuleState::NOT_FOUND, $module->getState());
    }

    public function testNotLoadableIfLoadErrors()
    {
        $moduleFile = new ModuleFile('vendor/name');
        $module = new Module($moduleFile, __DIR__, null, array(
            new RuntimeException('Could not load module'),
        ));

        $this->assertSame(ModuleState::NOT_LOADABLE, $module->getState());
    }

    public function testCreateModuleWithoutModuleFileNorInstallInfo()
    {
        $module = new Module(null, '/path', null, array(new Exception()));

        $this->assertNull($module->getName());
    }
}
