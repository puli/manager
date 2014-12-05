<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Tag;

use Puli\RepositoryManager\Tag\TagMapping;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TagMappingTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfPuliSelectorNotString()
    {
        new TagMapping(12345, 'resources');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfPuliSelectorEmpty()
    {
        new TagMapping('', 'resources');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfTagsNotStringOrArray()
    {
        new TagMapping('/path', 12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfTagsEmptyString()
    {
        new TagMapping('/path', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfTagsNotStringArray()
    {
        new TagMapping('/path', array(12345));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfTagsContainEmptyString()
    {
        new TagMapping('/path', array(''));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNoTags()
    {
        new TagMapping('/path', array());
    }
}
