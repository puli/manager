<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Server;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Installer\InstallerManager;
use Puli\Manager\Api\Module\RootModuleFileManager;
use Puli\Manager\Api\Server\Server;
use Puli\Manager\Server\ModuleFileServerManager;
use Puli\Manager\Tests\TestException;
use Webmozart\Expression\Expr;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleFileServerManagerUnloadedTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PHPUnit_Framework_MockObject_MockObject|RootModuleFileManager
     */
    protected $moduleFileManager;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|InstallerManager
     */
    protected $installerManager;

    /**
     * @var ModuleFileServerManager
     */
    protected $serverManager;

    protected function setUp()
    {
        $this->moduleFileManager = $this->getMock('Puli\Manager\Api\Module\RootModuleFileManager');
        $this->installerManager = $this->getMock('Puli\Manager\Api\Installer\InstallerManager');
        $this->serverManager = new ModuleFileServerManager($this->moduleFileManager, $this->installerManager);

        $this->installerManager->expects($this->any())
            ->method('hasInstallerDescriptor')
            ->willReturnMap(array(
                array('symlink', true),
                array('rsync', true),
            ));
    }

    public function testGetServer()
    {
        $this->populateDefaultManager();

        $server = new Server('localhost', 'symlink', 'web', '/public/%s');

        $this->assertEquals($server, $this->serverManager->getServer('localhost'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Server\NoSuchServerException
     * @expectedExceptionMessage foobar
     */
    public function testGetServerFailsIfNotFound()
    {
        $this->populateDefaultManager();

        $this->serverManager->getServer('foobar');
    }

    public function testGetServerWithParameters()
    {
        $this->moduleFileManager->expects($this->any())
            ->method('getExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY)
            ->willReturn((object) array(
                'localhost' => (object) array(
                    'installer' => 'symlink',
                    'document-root' => 'web',
                    'url-format' => '/public/%s',
                    'parameters' => (object) array('param' => 'value'),
                ),
            ));

        $server = new Server('localhost', 'symlink', 'web', '/public/%s', array(
            'param' => 'value',
        ));

        $this->assertEquals($server, $this->serverManager->getServer('localhost'));
    }

    public function testGetServerWithoutUrlFormat()
    {
        $this->moduleFileManager->expects($this->any())
            ->method('getExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY)
            ->willReturn((object) array(
                'localhost' => (object) array(
                    'installer' => 'symlink',
                    'document-root' => 'web',
                ),
            ));

        $server = new Server('localhost', 'symlink', 'web');

        $this->assertEquals($server, $this->serverManager->getServer('localhost'));
    }

    /**
     * @expectedException \Webmozart\Json\ValidationFailedException
     */
    public function testFailIfKeyNotAnArray()
    {
        $this->moduleFileManager->expects($this->any())
            ->method('getExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY)
            ->willReturn('foobar');

        $this->serverManager->getServer('localhost');
    }

    public function testGetServers()
    {
        $this->populateDefaultManager();

        $server = new Server('localhost', 'symlink', 'web', '/public/%s');

        $collection = $this->serverManager->getServers();

        $this->assertInstanceOf('Puli\Manager\Api\Server\ServerCollection', $collection);
        $this->assertEquals(array('localhost' => $server), $collection->toArray());
    }

    public function testFindServers()
    {
        $this->moduleFileManager->expects($this->any())
            ->method('getExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY)
            ->willReturn((object) array(
                'localhost1' => (object) array(
                    'installer' => 'symlink',
                    'document-root' => 'web',
                    'url-format' => '/public/%s',
                ),
                'localhost2' => (object) array(
                    'installer' => 'copy',
                    'document-root' => 'alternative',
                    'url-format' => '/alternative/%s',
                ),
                'cdn' => (object) array(
                    'installer' => 'rsync',
                    'document-root' => 'ssh://my.cdn.com',
                    'parameters' => (object) array('param' => 'value'),
                ),
            ));

        $server1 = new Server('localhost1', 'symlink', 'web', '/public/%s');
        $server2 = new Server('localhost2', 'copy', 'alternative', '/alternative/%s');

        $collection = $this->serverManager->findServers(Expr::method('getName', Expr::startsWith('localhost')));

        $this->assertInstanceOf('Puli\Manager\Api\Server\ServerCollection', $collection);
        $this->assertEquals(array('localhost1' => $server1, 'localhost2' => $server2), $collection->toArray());
    }

    public function testHasServer()
    {
        $this->populateDefaultManager();

        $this->assertTrue($this->serverManager->hasServer('localhost'));
        $this->assertFalse($this->serverManager->hasServer('cdn'));
    }

    public function testHasServers()
    {
        $this->populateDefaultManager();

        $this->assertTrue($this->serverManager->hasServers());
        $this->assertTrue($this->serverManager->hasServers(Expr::method('getName', Expr::same('localhost'))));
        $this->assertFalse($this->serverManager->hasServers(Expr::method('getName', Expr::same('foobar'))));
    }

    public function testHasNoServers()
    {
        $this->moduleFileManager->expects($this->any())
            ->method('getExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY)
            ->willReturn(null);

        $this->assertFalse($this->serverManager->hasServers());
    }

    public function testAddServer()
    {
        $this->populateDefaultManager();

        $this->moduleFileManager->expects($this->once())
            ->method('setExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY, (object) array(
                'localhost' => (object) array(
                    'installer' => 'symlink',
                    'document-root' => 'web',
                    'url-format' => '/public/%s',
                ),
                'cdn' => (object) array(
                    'installer' => 'rsync',
                    'document-root' => 'ssh://my.cdn.com',
                ),
            ));

        $server = new Server('cdn', 'rsync', 'ssh://my.cdn.com');

        $this->serverManager->addServer($server);

        $this->assertSame($server, $this->serverManager->getServer('cdn'));
    }

    public function testAddServerWithUrlFormat()
    {
        $this->populateDefaultManager();

        $this->moduleFileManager->expects($this->once())
            ->method('setExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY, (object) array(
                'localhost' => (object) array(
                    'installer' => 'symlink',
                    'document-root' => 'web',
                    'url-format' => '/public/%s',
                ),
                'cdn' => (object) array(
                    'installer' => 'rsync',
                    'document-root' => 'ssh://my.cdn.com',
                    'url-format' => 'http://my.cdn.com/%s',
                ),
            ));

        $server = new Server('cdn', 'rsync', 'ssh://my.cdn.com', 'http://my.cdn.com/%s');

        $this->serverManager->addServer($server);

        $this->assertSame($server, $this->serverManager->getServer('cdn'));
    }

    public function testAddServerWithParameters()
    {
        $this->populateDefaultManager();

        $this->moduleFileManager->expects($this->once())
            ->method('setExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY, (object) array(
                'localhost' => (object) array(
                    'installer' => 'symlink',
                    'document-root' => 'web',
                    'url-format' => '/public/%s',
                ),
                'cdn' => (object) array(
                    'installer' => 'rsync',
                    'document-root' => 'ssh://my.cdn.com',
                    'parameters' => (object) array('param' => 'value'),
                ),
            ));

        $server = new Server('cdn', 'rsync', 'ssh://my.cdn.com', '/%s', array(
            'param' => 'value',
        ));

        $this->serverManager->addServer($server);

        $this->assertSame($server, $this->serverManager->getServer('cdn'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Installer\NoSuchInstallerException
     */
    public function testAddServerFailsIfInstallerNotFound()
    {
        $this->populateDefaultManager();

        $this->moduleFileManager->expects($this->never())
            ->method('setExtraKey');

        $server = new Server('cdn', 'foobar', 'ssh://my.cdn.com');

        $this->serverManager->addServer($server);
    }

    public function testAddServerRevertsIfSavingFails()
    {
        $this->populateDefaultManager();

        $this->moduleFileManager->expects($this->any())
            ->method('getExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY)
            ->willReturn((object) array(
                'localhost' => (object) array(
                    'installer' => 'symlink',
                    'document-root' => 'web',
                    'url-format' => '/public/%s',
                ),
            ));

        $this->moduleFileManager->expects($this->once())
            ->method('setExtraKey')
            ->willThrowException(new TestException());

        $server = new Server('cdn', 'rsync', 'ssh://my.cdn.com');

        try {
            $this->serverManager->addServer($server);
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($this->serverManager->hasServer('localhost'));
        $this->assertFalse($this->serverManager->hasServer('cdn'));
    }

    public function testRemoveServer()
    {
        $this->moduleFileManager->expects($this->any())
            ->method('getExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY)
            ->willReturn((object) array(
                'localhost' => (object) array(
                    'installer' => 'symlink',
                    'document-root' => 'web',
                    'url-format' => '/public/%s',
                ),
                'cdn' => (object) array(
                    'installer' => 'rsync',
                    'document-root' => 'ssh://my.cdn.com',
                    'parameters' => (object) array('param' => 'value'),
                ),
            ));

        $this->moduleFileManager->expects($this->once())
            ->method('setExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY, (object) array(
                'cdn' => (object) array(
                    'installer' => 'rsync',
                    'document-root' => 'ssh://my.cdn.com',
                    'parameters' => (object) array('param' => 'value'),
                ),
            ));

        $this->serverManager->removeServer('localhost');

        $this->assertFalse($this->serverManager->hasServer('localhost'));
        $this->assertTrue($this->serverManager->hasServer('cdn'));
    }

    public function testRemoveLastServer()
    {
        $this->populateDefaultManager();

        $this->moduleFileManager->expects($this->once())
            ->method('removeExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY);

        $this->serverManager->removeServer('localhost');

        $this->assertFalse($this->serverManager->hasServer('localhost'));
    }

    public function testRemoveNonExistingServer()
    {
        $this->populateDefaultManager();

        $this->moduleFileManager->expects($this->never())
            ->method('setExtraKey');
        $this->moduleFileManager->expects($this->never())
            ->method('removeExtraKey');

        $this->serverManager->removeServer('foobar');

        $this->assertTrue($this->serverManager->hasServer('localhost'));
    }

    public function testRemoveServerRevertsIfSavingFails()
    {
        $this->moduleFileManager->expects($this->any())
            ->method('getExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY)
            ->willReturn((object) array(
                'localhost' => (object) array(
                    'installer' => 'symlink',
                    'document-root' => 'web',
                    'url-format' => '/public/%s',
                ),
                'cdn' => (object) array(
                    'installer' => 'rsync',
                    'document-root' => 'ssh://my.cdn.com',
                    'parameters' => (object) array('param' => 'value'),
                ),
            ));

        $this->moduleFileManager->expects($this->once())
            ->method('setExtraKey')
            ->willThrowException(new TestException());

        try {
            $this->serverManager->removeServer('localhost');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($this->serverManager->hasServer('localhost'));
        $this->assertTrue($this->serverManager->hasServer('cdn'));
    }

    public function testRemoveServers()
    {
        $this->moduleFileManager->expects($this->any())
            ->method('getExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY)
            ->willReturn((object) array(
                'localhost1' => (object) array(
                    'installer' => 'symlink',
                    'document-root' => 'web',
                    'url-format' => '/public/%s',
                ),
                'localhost2' => (object) array(
                    'installer' => 'copy',
                    'document-root' => 'alternative',
                    'url-format' => '/alternative/%s',
                ),
                'cdn' => (object) array(
                    'installer' => 'rsync',
                    'document-root' => 'ssh://my.cdn.com',
                    'parameters' => (object) array('param' => 'value'),
                ),
            ));

        $this->moduleFileManager->expects($this->once())
            ->method('setExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY, (object) array(
                'cdn' => (object) array(
                    'installer' => 'rsync',
                    'document-root' => 'ssh://my.cdn.com',
                    'parameters' => (object) array('param' => 'value'),
                ),
            ));

        $this->serverManager->removeServers(Expr::method('getName', Expr::startsWith('localhost')));

        $this->assertFalse($this->serverManager->hasServer('localhost1'));
        $this->assertFalse($this->serverManager->hasServer('localhost2'));
        $this->assertTrue($this->serverManager->hasServer('cdn'));
    }

    public function testRemoveServersRevertsIfSavingFails()
    {
        $this->moduleFileManager->expects($this->any())
            ->method('getExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY)
            ->willReturn((object) array(
                'localhost' => (object) array(
                    'installer' => 'symlink',
                    'document-root' => 'web',
                    'url-format' => '/public/%s',
                ),
                'cdn' => (object) array(
                    'installer' => 'rsync',
                    'document-root' => 'ssh://my.cdn.com',
                    'parameters' => (object) array('param' => 'value'),
                ),
            ));

        $this->moduleFileManager->expects($this->once())
            ->method('setExtraKey')
            ->willThrowException(new TestException());

        try {
            $this->serverManager->removeServers(Expr::method('getName', Expr::startsWith('localhost')));
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($this->serverManager->hasServer('localhost'));
        $this->assertTrue($this->serverManager->hasServer('cdn'));
    }

    public function testClearServers()
    {
        $this->moduleFileManager->expects($this->any())
            ->method('getExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY)
            ->willReturn((object) array(
                'localhost' => (object) array(
                    'installer' => 'symlink',
                    'document-root' => 'web',
                    'url-format' => '/public/%s',
                ),
                'cdn' => (object) array(
                    'installer' => 'rsync',
                    'document-root' => 'ssh://my.cdn.com',
                    'parameters' => (object) array('param' => 'value'),
                ),
            ));

        $this->moduleFileManager->expects($this->once())
            ->method('removeExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY);

        $this->serverManager->clearServers();

        $this->assertFalse($this->serverManager->hasServer('localhost1'));
        $this->assertFalse($this->serverManager->hasServer('cdn'));
    }

    protected function populateDefaultManager()
    {
        $this->moduleFileManager->expects($this->any())
            ->method('getExtraKey')
            ->with(ModuleFileServerManager::SERVERS_KEY)
            ->willReturn((object) array(
                'localhost' => (object) array(
                    'installer' => 'symlink',
                    'document-root' => 'web',
                    'url-format' => '/public/%s',
                ),
            ));
    }
}
