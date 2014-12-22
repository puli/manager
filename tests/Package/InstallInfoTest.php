<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Binding\BindingDescriptor;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallInfoTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $installInfo = new InstallInfo('package', '/path');
        $installInfo->setInstaller('Composer');

        $this->assertSame('package', $installInfo->getPackageName());
        $this->assertSame('/path', $installInfo->getInstallPath());
        $this->assertSame('Composer', $installInfo->getInstaller());
    }
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallPathNotString()
    {
        new InstallInfo('package', 12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallPathEmpty()
    {
        new InstallInfo('package', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameNotString()
    {
        new InstallInfo(12345, '/path');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameEmpty()
    {
        new InstallInfo('', '/path');
    }

    public function testAddEnabledBinding()
    {
        $installInfo = new InstallInfo('package', '/path');
        $binding = new BindingDescriptor('/bound-path', 'type');

        $installInfo->addEnabledBinding($binding);

        $this->assertSame(array($binding), $installInfo->getEnabledBindings());
    }

    public function testAddEnabledBindingIgnoresDuplicates()
    {
        $installInfo = new InstallInfo('package', '/path');
        $binding = new BindingDescriptor('/bound-path', 'type');

        $installInfo->addEnabledBinding($binding);
        $installInfo->addEnabledBinding($binding);

        $this->assertSame(array($binding), $installInfo->getEnabledBindings());
    }

    public function testRemoveEnabledBinding()
    {
        $installInfo = new InstallInfo('package', '/path');
        $binding = new BindingDescriptor('/bound-path', 'type');

        $installInfo->addEnabledBinding($binding);
        $installInfo->removeEnabledBinding($binding);

        $this->assertSame(array(), $installInfo->getEnabledBindings());
    }

    public function testRemoveEnabledBindingIgnoresUnknown()
    {
        $installInfo = new InstallInfo('package', '/path');
        $binding = new BindingDescriptor('/bound-path', 'type');

        $installInfo->removeEnabledBinding($binding);

        $this->assertSame(array(), $installInfo->getEnabledBindings());
    }

    public function testAddDisabledBinding()
    {
        $installInfo = new InstallInfo('package', '/path');
        $binding = new BindingDescriptor('/bound-path', 'type');

        $installInfo->addDisabledBinding($binding);

        $this->assertSame(array($binding), $installInfo->getDisabledBindings());
    }

    public function testAddDisabledBindingIgnoresDuplicates()
    {
        $installInfo = new InstallInfo('package', '/path');
        $binding = new BindingDescriptor('/bound-path', 'type');

        $installInfo->addDisabledBinding($binding);
        $installInfo->addDisabledBinding($binding);

        $this->assertSame(array($binding), $installInfo->getDisabledBindings());
    }

    public function testRemoveDisabledBinding()
    {
        $installInfo = new InstallInfo('package', '/path');
        $binding = new BindingDescriptor('/bound-path', 'type');

        $installInfo->addDisabledBinding($binding);
        $installInfo->removeDisabledBinding($binding);

        $this->assertSame(array(), $installInfo->getDisabledBindings());
    }

    public function testRemoveDisabledBindingIgnoresUnknown()
    {
        $installInfo = new InstallInfo('package', '/path');
        $binding = new BindingDescriptor('/bound-path', 'type');

        $installInfo->removeDisabledBinding($binding);

        $this->assertSame(array(), $installInfo->getDisabledBindings());
    }

    public function testAddEnabledBindingRemovesDisabledMapping()
    {
        $installInfo = new InstallInfo('package', '/path');
        $binding = new BindingDescriptor('/bound-path', 'type');

        $installInfo->addDisabledBinding($binding);
        $installInfo->addEnabledBinding($binding);

        $this->assertSame(array($binding), $installInfo->getEnabledBindings());
        $this->assertSame(array(), $installInfo->getDisabledBindings());
    }

    public function testAddDisabledBindingRemovesEnabledMapping()
    {
        $installInfo = new InstallInfo('package', '/path');
        $binding = new BindingDescriptor('/bound-path', 'type');

        $installInfo->addEnabledBinding($binding);
        $installInfo->addDisabledBinding($binding);

        $this->assertSame(array(), $installInfo->getEnabledBindings());
        $this->assertSame(array($binding), $installInfo->getDisabledBindings());
    }
}
