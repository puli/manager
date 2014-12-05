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

use Puli\RepositoryManager\Package\TagDescriptor;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TagDescriptorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfPuliSelectorNotString()
    {
        new TagDescriptor(12345, 'resources');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfPuliSelectorEmpty()
    {
        new TagDescriptor('', 'resources');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfTagsNotStringOrArray()
    {
        new TagDescriptor('/path', 12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfTagsEmptyString()
    {
        new TagDescriptor('/path', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfTagsNotStringArray()
    {
        new TagDescriptor('/path', array(12345));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfTagsContainEmptyString()
    {
        new TagDescriptor('/path', array(''));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNoTags()
    {
        new TagDescriptor('/path', array());
    }
}
