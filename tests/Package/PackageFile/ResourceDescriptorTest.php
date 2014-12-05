<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\PackageFile;

use Puli\RepositoryManager\Package\PackageFile\ResourceDescriptor;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResourceDescriptorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfPuliPathNotString()
    {
        new ResourceDescriptor(12345, 'resources');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfPuliPathEmpty()
    {
        new ResourceDescriptor('', 'resources');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfLocalPathsNotStringOrArray()
    {
        new ResourceDescriptor('/path', 12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfLocalPathsEmptyString()
    {
        new ResourceDescriptor('/path', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfLocalPathsNotStringArray()
    {
        new ResourceDescriptor('/path', array(12345));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfLocalPathsContainEmptyString()
    {
        new ResourceDescriptor('/path', array(''));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNoLocalPaths()
    {
        new ResourceDescriptor('/path', array());
    }
}
