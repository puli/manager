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

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\Module\RootModuleFile;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootModuleTest extends PHPUnit_Framework_TestCase
{
    public function testModuleName()
    {
        $moduleFile = new RootModuleFile('vendor/name');
        $module = new RootModule($moduleFile, '/path');

        $this->assertSame('vendor/name', $module->getName());
    }

    public function testModuleNameSetToDefaultIfEmpty()
    {
        $moduleFile = new RootModuleFile();
        $module = new RootModule($moduleFile, '/path');

        $this->assertSame('__root__', $module->getName());
    }
}
