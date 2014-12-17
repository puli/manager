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
use Puli\RepositoryManager\Package\ResourceMapping;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResourceMappingTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfPuliPathNotString()
    {
        new ResourceMapping(12345, 'resources');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfPuliPathEmpty()
    {
        new ResourceMapping('', 'resources');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfLocalPathsNotStringOrArray()
    {
        new ResourceMapping('/path', 12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfLocalPathsEmptyString()
    {
        new ResourceMapping('/path', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfLocalPathsNotStringArray()
    {
        new ResourceMapping('/path', array(12345));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfLocalPathsContainEmptyString()
    {
        new ResourceMapping('/path', array(''));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNoLocalPaths()
    {
        new ResourceMapping('/path', array());
    }
}
