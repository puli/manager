<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Server;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Server\Server;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ServerTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $server = new Server('localhost', 'symlink', 'web/assets', '/assets/%s');

        $this->assertSame('localhost', $server->getName());
        $this->assertSame('symlink', $server->getInstallerName());
        $this->assertSame('web/assets', $server->getDocumentRoot());
        $this->assertSame('/assets/%s', $server->getUrlFormat());
        $this->assertSame(array(), $server->getParameterValues());
    }

    public function testCreateWithParameter()
    {
        $server = new Server('localhost', 'symlink', 'web/assets', '/%s', array(
            'param1' => 'webmozart',
        ));

        $this->assertSame(array('param1' => 'webmozart'), $server->getParameterValues());
    }

    public function testCreateWithDefaultUrlFormat()
    {
        $server = new Server('localhost', 'symlink', 'web/assets');

        $this->assertSame('/%s', $server->getUrlFormat());
        $this->assertSame(array(), $server->getParameterValues());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameNull()
    {
        new Server(null, 'symlink', 'web/assets');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameEmpty()
    {
        new Server('', 'symlink', 'web/assets');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameNoString()
    {
        new Server(1234, 'symlink', 'web/assets');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallerNameNull()
    {
        new Server('localhost', null, 'web/assets');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallerNameEmpty()
    {
        new Server('localhost', '', 'web/assets');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallerNameNoString()
    {
        new Server('localhost', 1234, 'web/assets');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfLocationNull()
    {
        new Server('localhost', 'symlink', null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfLocationEmpty()
    {
        new Server('localhost', 'symlink', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfLocationNoString()
    {
        new Server('localhost', 'symlink', 1234);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfUrlFormatNull()
    {
        new Server('localhost', 'symlink', 'web/assets', null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfUrlFormatEmpty()
    {
        new Server('localhost', 'symlink', 'web/assets', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfUrlFormatNoString()
    {
        new Server('localhost', 'symlink', 'web/assets', 1234);
    }

    public function testGetParameterValue()
    {
        $server = new Server('localhost', 'symlink', 'web', '/%s', array('param1' => 'value1', 'param2' => 'value2'));

        $this->assertSame('value1', $server->getParameterValue('param1'));
        $this->assertSame('value2', $server->getParameterValue('param2'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Installer\NoSuchParameterException
     * @expectedExceptionMessage foobar
     */
    public function testGetParameterValueFailsIfNotFound()
    {
        $server = new Server('localhost', 'symlink', 'web');

        $server->getParameterValue('foobar');
    }

    public function testHasParameterValue()
    {
        $server = new Server('localhost', 'symlink', 'web', '/%s', array('param1' => 'value1', 'param2' => 'value2'));

        $this->assertTrue($server->hasParameterValue('param1'));
        $this->assertTrue($server->hasParameterValue('param2'));
        $this->assertFalse($server->hasParameterValue('foo'));
    }

    public function testHasParameterValues()
    {
        $server = new Server('localhost', 'symlink', 'web', '/%s', array('param1' => 'value1'));

        $this->assertTrue($server->hasParameterValues());
    }

    public function testHasNoParameterValues()
    {
        $server = new Server('localhost', 'symlink', 'web', '/%s', array());

        $this->assertFalse($server->hasParameterValues());
    }
}
