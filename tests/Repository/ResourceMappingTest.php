<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Repository;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Repository\ResourceMapping;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResourceMappingTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfRepositoryPathNotString()
    {
        new ResourceMapping(12345, 'resources');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfRepositoryPathEmpty()
    {
        new ResourceMapping('', 'resources');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfFilesystemPathsNotStringOrArray()
    {
        new ResourceMapping('/path', 12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfFilesystemPathsEmptyString()
    {
        new ResourceMapping('/path', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfFilesystemPathsNotStringArray()
    {
        new ResourceMapping('/path', array(12345));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfFilesystemPathsContainEmptyString()
    {
        new ResourceMapping('/path', array(''));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNoFilesystemPaths()
    {
        new ResourceMapping('/path', array());
    }
}
