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

use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Tag\TagMapping;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallInfoTest extends \PHPUnit_Framework_TestCase
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

    public function testAddEnabledTagMapping()
    {
        $installInfo = new InstallInfo('package', '/path');
        $mapping = new TagMapping('/tagged-path', 'tag1');

        $installInfo->addEnabledTagMapping($mapping);

        $this->assertSame(array($mapping), $installInfo->getEnabledTagMappings());
    }

    public function testAddEnabledTagMappingIgnoresDuplicates()
    {
        $installInfo = new InstallInfo('package', '/path');
        $mapping = new TagMapping('/tagged-path', 'tag1');

        $installInfo->addEnabledTagMapping($mapping);
        $installInfo->addEnabledTagMapping($mapping);

        $this->assertSame(array($mapping), $installInfo->getEnabledTagMappings());
    }

    public function testRemoveEnabledTagMapping()
    {
        $installInfo = new InstallInfo('package', '/path');
        $mapping = new TagMapping('/tagged-path', 'tag1');

        $installInfo->addEnabledTagMapping($mapping);
        $installInfo->removeEnabledTagMapping($mapping);

        $this->assertSame(array(), $installInfo->getEnabledTagMappings());
    }

    public function testRemoveEnabledTagMappingIgnoresUnknown()
    {
        $installInfo = new InstallInfo('package', '/path');
        $mapping = new TagMapping('/tagged-path', 'tag1');

        $installInfo->removeEnabledTagMapping($mapping);

        $this->assertSame(array(), $installInfo->getEnabledTagMappings());
    }

    public function testAddDisabledTagMapping()
    {
        $installInfo = new InstallInfo('package', '/path');
        $mapping = new TagMapping('/tagged-path', 'tag1');

        $installInfo->addDisabledTagMapping($mapping);

        $this->assertSame(array($mapping), $installInfo->getDisabledTagMappings());
    }

    public function testAddDisabledTagMappingIgnoresDuplicates()
    {
        $installInfo = new InstallInfo('package', '/path');
        $mapping = new TagMapping('/tagged-path', 'tag1');

        $installInfo->addDisabledTagMapping($mapping);
        $installInfo->addDisabledTagMapping($mapping);

        $this->assertSame(array($mapping), $installInfo->getDisabledTagMappings());
    }

    public function testRemoveDisabledTagMapping()
    {
        $installInfo = new InstallInfo('package', '/path');
        $mapping = new TagMapping('/tagged-path', 'tag1');

        $installInfo->addDisabledTagMapping($mapping);
        $installInfo->removeDisabledTagMapping($mapping);

        $this->assertSame(array(), $installInfo->getDisabledTagMappings());
    }

    public function testRemoveDisabledTagMappingIgnoresUnknown()
    {
        $installInfo = new InstallInfo('package', '/path');
        $mapping = new TagMapping('/tagged-path', 'tag1');

        $installInfo->removeDisabledTagMapping($mapping);

        $this->assertSame(array(), $installInfo->getDisabledTagMappings());
    }

    public function testAddEnabledTagMappingRemovesDisabledMapping()
    {
        $installInfo = new InstallInfo('package', '/path');
        $mapping = new TagMapping('/tagged-path', 'tag1');

        $installInfo->addDisabledTagMapping($mapping);
        $installInfo->addEnabledTagMapping($mapping);

        $this->assertSame(array($mapping), $installInfo->getEnabledTagMappings());
        $this->assertSame(array(), $installInfo->getDisabledTagMappings());
    }

    public function testAddDisabledTagMappingRemovesEnabledMapping()
    {
        $installInfo = new InstallInfo('package', '/path');
        $mapping = new TagMapping('/tagged-path', 'tag1');

        $installInfo->addEnabledTagMapping($mapping);
        $installInfo->addDisabledTagMapping($mapping);

        $this->assertSame(array(), $installInfo->getEnabledTagMappings());
        $this->assertSame(array($mapping), $installInfo->getDisabledTagMappings());
    }
}
