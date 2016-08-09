<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Asset;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\Discovery\Api\Binding\Binding;
use Puli\Discovery\Api\Type\BindingType;
use Puli\Manager\Api\Asset\AssetManager;
use Puli\Manager\Api\Asset\AssetMapping;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Event\AddAssetMappingEvent;
use Puli\Manager\Api\Event\PuliEvents;
use Puli\Manager\Api\Event\RemoveAssetMappingEvent;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Api\Server\Server;
use Puli\Manager\Api\Server\ServerCollection;
use Puli\Manager\Asset\DiscoveryAssetManager;
use Puli\Repository\Discovery\ResourceBinding;
use Puli\UrlGenerator\DiscoveryUrlGenerator;
use Rhumsaa\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webmozart\Expression\Expr;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DiscoveryAssetManagerTest extends PHPUnit_Framework_TestCase
{
    const RESOURCE_BINDING = 'Puli\Repository\Discovery\ResourceBinding';

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|DiscoveryManager
     */
    private $discoveryManager;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var Server
     */
    private $server1;

    /**
     * @var Server
     */
    private $server2;

    /**
     * @var ServerCollection
     */
    private $servers;

    /**
     * @var Module
     */
    private $module;

    /**
     * @var RootModule
     */
    private $rootModule;

    /**
     * @var BindingType
     */
    private $bindingType;

    /**
     * @var BindingTypeDescriptor
     */
    private $typeDescriptor;

    /**
     * @var Binding
     */
    private $binding1;

    /**
     * @var Binding
     */
    private $binding2;

    /**
     * @var BindingDescriptor
     */
    private $bindingDescriptor1;

    /**
     * @var BindingDescriptor
     */
    private $bindingDescriptor2;

    /**
     * @var DiscoveryAssetManager
     */
    private $manager;

    protected function setUp()
    {
        $this->discoveryManager = $this->getMock('Puli\Manager\Api\Discovery\DiscoveryManager');
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->server1 = new Server('target1', 'symlink', 'public_html');
        $this->server2 = new Server('target2', 'rsync', 'ssh://server');
        $this->servers = new ServerCollection(array($this->server1, $this->server2));
        $this->manager = new DiscoveryAssetManager($this->discoveryManager, $this->servers, $this->dispatcher);
        $this->module = new Module(new ModuleFile('vendor/module'), '/path');
        $this->rootModule = new RootModule(new RootModuleFile('vendor/root'), '/path');
        $this->bindingType = new BindingType(DiscoveryUrlGenerator::BINDING_TYPE, self::RESOURCE_BINDING);
        $this->typeDescriptor = new BindingTypeDescriptor($this->bindingType);
        $this->binding1 = new ResourceBinding(
            '/path{,/**/*}',
            DiscoveryUrlGenerator::BINDING_TYPE,
            array(
                DiscoveryUrlGenerator::SERVER_PARAMETER => 'target1',
                DiscoveryUrlGenerator::PATH_PARAMETER => '/css',
            )
        );
        $this->binding2 = new ResourceBinding(
            '/other/path{,/**/*}',
            DiscoveryUrlGenerator::BINDING_TYPE,
            array(
                DiscoveryUrlGenerator::SERVER_PARAMETER => 'target2',
                DiscoveryUrlGenerator::PATH_PARAMETER => '/js',
            )
        );
        $this->bindingDescriptor1 = new BindingDescriptor($this->binding1);
        $this->bindingDescriptor2 = new BindingDescriptor($this->binding2);
    }

    public function testAddRootAssetMapping()
    {
        $mapping = new AssetMapping('/path', 'target1', '/css');

        $expectedBinding = new BindingDescriptor(new ResourceBinding(
            '/path{,/**/*}',
            DiscoveryUrlGenerator::BINDING_TYPE,
            array(
                DiscoveryUrlGenerator::SERVER_PARAMETER => 'target1',
                DiscoveryUrlGenerator::PATH_PARAMETER => '/css',
            ),
            'glob'
        ));

        $this->discoveryManager->expects($this->once())
            ->method('hasTypeDescriptor')
            ->with(DiscoveryUrlGenerator::BINDING_TYPE)
            ->willReturn(true);

        $this->discoveryManager->expects($this->once())
            ->method('hasBindingDescriptors')
            ->with($this->mapping($mapping))
            ->willReturn(false);

        $this->discoveryManager->expects($this->once())
            ->method('addRootBindingDescriptor')
            ->with($expectedBinding);

        $this->manager->addRootAssetMapping($mapping);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage puli/url-generator
     */
    public function testAddRootAssetMappingFailsIfTypeNotAvailable()
    {
        $this->discoveryManager->expects($this->once())
            ->method('hasTypeDescriptor')
            ->with(DiscoveryUrlGenerator::BINDING_TYPE)
            ->willReturn(false);

        $this->discoveryManager->expects($this->never())
            ->method('hasBindingDescriptors');

        $this->discoveryManager->expects($this->never())
            ->method('addRootBindingDescriptor');

        $this->manager->addRootAssetMapping(new AssetMapping('/path', 'target1', '/css'));
    }

    public function testAddRootAssetMappingDispatchesEvent()
    {
        $uuid = Uuid::uuid4();
        $mapping = new AssetMapping('/path', 'target1', '/css', $uuid);
        $event = new AddAssetMappingEvent($mapping);

        $this->discoveryManager->expects($this->once())
            ->method('hasTypeDescriptor')
            ->with(DiscoveryUrlGenerator::BINDING_TYPE)
            ->willReturn(true);

        $this->discoveryManager->expects($this->once())
            ->method('hasBindingDescriptors')
            ->with($this->mapping($mapping))
            ->willReturn(false);

        $this->dispatcher->expects($this->any())
            ->method('hasListeners')
            ->with(PuliEvents::POST_ADD_ASSET_MAPPING)
            ->willReturn(true);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PuliEvents::POST_ADD_ASSET_MAPPING, $event);

        $this->manager->addRootAssetMapping($mapping);
    }

    /**
     * @expectedException \Puli\Manager\Api\Server\NoSuchServerException
     * @expectedExceptionMessage foobar
     */
    public function testAddRootAssetMappingFailsIfServerNotFound()
    {
        $this->discoveryManager->expects($this->once())
            ->method('hasTypeDescriptor')
            ->with(DiscoveryUrlGenerator::BINDING_TYPE)
            ->willReturn(true);

        $this->discoveryManager->expects($this->never())
            ->method('hasBindingDescriptors');

        $this->discoveryManager->expects($this->never())
            ->method('addRootBindingDescriptor');

        $this->manager->addRootAssetMapping(new AssetMapping('/path', 'foobar', '/css'));
    }

    public function testAddRootAssetMappingDoesNotFailIfServerNotFoundAndIgnoreServerNotFound()
    {
        $mapping = new AssetMapping('/path', 'foobar', '/css');

        $expectedBinding = new BindingDescriptor(new ResourceBinding(
            '/path{,/**/*}',
            DiscoveryUrlGenerator::BINDING_TYPE,
            array(
                DiscoveryUrlGenerator::SERVER_PARAMETER => 'foobar',
                DiscoveryUrlGenerator::PATH_PARAMETER => '/css',
            ),
            'glob'
        ));

        $this->discoveryManager->expects($this->once())
            ->method('hasTypeDescriptor')
            ->with(DiscoveryUrlGenerator::BINDING_TYPE)
            ->willReturn(true);

        $this->discoveryManager->expects($this->once())
            ->method('hasBindingDescriptors')
            ->with($this->mapping($mapping))
            ->willReturn(false);

        $this->discoveryManager->expects($this->once())
            ->method('addRootBindingDescriptor')
            ->with($expectedBinding);

        $this->manager->addRootAssetMapping($mapping, AssetManager::IGNORE_SERVER_NOT_FOUND);
    }

    /**
     * @expectedException \Puli\Manager\Api\Asset\DuplicateAssetMappingException
     * @expectedExceptionMessage The asset mapping with glob "/path", server name "target1" and path "/css" exists already.
     */
    public function testAddRootAssetMappingFailsIfExistsAlready()
    {
        $mapping = new AssetMapping('/path', 'target1', '/css');

        $this->discoveryManager->expects($this->once())
            ->method('hasTypeDescriptor')
            ->with(DiscoveryUrlGenerator::BINDING_TYPE)
            ->willReturn(true);

        $this->discoveryManager->expects($this->once())
            ->method('hasBindingDescriptors')
            ->with($this->mapping($mapping))
            ->willReturn(true);

        $this->discoveryManager->expects($this->never())
            ->method('addRootBindingDescriptor');

        $this->manager->addRootAssetMapping($mapping);
    }

    public function testAddRootAssetMappingDoesNotFailIfUuidExistsAlreadyAndOverride()
    {
        $uuid = Uuid::fromString('76e83c4e-2c0d-44de-b1cb-57a3e0d925a1');

        $expectedBinding = new BindingDescriptor(new ResourceBinding(
            '/path{,/**/*}',
            DiscoveryUrlGenerator::BINDING_TYPE,
            array(
                DiscoveryUrlGenerator::SERVER_PARAMETER => 'target1',
                DiscoveryUrlGenerator::PATH_PARAMETER => '/css',
            ),
            'glob',
            $uuid
        ));

        $this->discoveryManager->expects($this->once())
            ->method('hasTypeDescriptor')
            ->with(DiscoveryUrlGenerator::BINDING_TYPE)
            ->willReturn(true);

        $this->discoveryManager->expects($this->never())
            ->method('hasBindingDescriptors');

        $this->discoveryManager->expects($this->once())
            ->method('addRootBindingDescriptor')
            ->with($expectedBinding, DiscoveryManager::OVERRIDE);

        $this->manager->addRootAssetMapping(new AssetMapping('/path', 'target1', '/css', $uuid), AssetManager::OVERRIDE);
    }

    public function testRemoveRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('removeRootBindingDescriptors')
            ->with($this->defaultExpr()->andMethod(
                'getParameterValue',
                DiscoveryUrlGenerator::SERVER_PARAMETER,
                Expr::same('target1')
            ));

        $this->manager->removeRootAssetMappings(Expr::method('getServerName', Expr::same('target1')));
    }

    public function testRemoveRootAssetMappingsDispatchesEvent()
    {
        $mapping1 = new AssetMapping('/path', 'target1', '/css');
        $mapping2 = new AssetMapping('/other/path', 'target2', '/js');
        $event1 = new RemoveAssetMappingEvent($mapping1);
        $event2 = new RemoveAssetMappingEvent($mapping2);

        $expr = $this->defaultExpr()->andMethod(
            'getParameterValue',
            DiscoveryUrlGenerator::SERVER_PARAMETER,
            Expr::same('target1')
        );

        $this->dispatcher->expects($this->at(0))
            ->method('hasListeners')
            ->with(PuliEvents::POST_REMOVE_ASSET_MAPPING)
            ->willReturn(true);

        $this->dispatcher->expects($this->at(1))
            ->method('dispatch')
            ->with(PuliEvents::POST_REMOVE_ASSET_MAPPING, $event1);

        $this->dispatcher->expects($this->at(2))
            ->method('dispatch')
            ->with(PuliEvents::POST_REMOVE_ASSET_MAPPING, $event2);

        $this->discoveryManager->expects($this->once())
            ->method('findRootBindingDescriptors')
            ->with($expr)
            ->willReturn(array($this->bindingDescriptor1, $this->bindingDescriptor2));

        $this->discoveryManager->expects($this->once())
            ->method('removeRootBindingDescriptors')
            ->with($expr);

        $this->manager->removeRootAssetMappings(Expr::method('getServerName', Expr::same('target1')));
    }

    public function testClearRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('removeRootBindingDescriptors')
            ->with($this->defaultExpr());

        $this->manager->clearRootAssetMappings();
    }

    public function testClearRootAssetMappingsDispatchesEvent()
    {
        $mapping1 = new AssetMapping('/path', 'target1', '/css');
        $mapping2 = new AssetMapping('/other/path', 'target2', '/js');
        $event1 = new RemoveAssetMappingEvent($mapping1);
        $event2 = new RemoveAssetMappingEvent($mapping2);

        $this->dispatcher->expects($this->at(0))
            ->method('hasListeners')
            ->with(PuliEvents::POST_REMOVE_ASSET_MAPPING)
            ->willReturn(true);

        $this->dispatcher->expects($this->at(1))
            ->method('dispatch')
            ->with(PuliEvents::POST_REMOVE_ASSET_MAPPING, $event1);

        $this->dispatcher->expects($this->at(2))
            ->method('dispatch')
            ->with(PuliEvents::POST_REMOVE_ASSET_MAPPING, $event2);

        $this->discoveryManager->expects($this->once())
            ->method('findRootBindingDescriptors')
            ->with($this->defaultExpr())
            ->willReturn(array($this->bindingDescriptor1, $this->bindingDescriptor2));

        $this->discoveryManager->expects($this->once())
            ->method('removeRootBindingDescriptors')
            ->with($this->defaultExpr());

        $this->manager->clearRootAssetMappings();
    }

    public function testGetAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->with($this->defaultExpr())
            ->willReturn(array($this->bindingDescriptor1, $this->bindingDescriptor2));

        $expected = array(
            new AssetMapping('/path', 'target1', '/css'),
            new AssetMapping('/other/path', 'target2', '/js'),
        );

        $this->assertEquals($expected, $this->manager->getAssetMappings());
    }

    public function testGetNoAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->with($this->defaultExpr())
            ->willReturn(array());

        $this->assertEquals(array(), $this->manager->getAssetMappings());
    }

    public function testFindAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->with($this->webPath('/other/path'))
            ->willReturn(array($this->bindingDescriptor2));

        $expr = Expr::method('getServerPath', Expr::same('/other/path'));
        $expected = new AssetMapping('/other/path', 'target2', '/js');

        $this->assertEquals(array($expected), $this->manager->findAssetMappings($expr));
    }

    public function testFindNoAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findBindingDescriptors')
            ->with($this->webPath('/foobar'))
            ->willReturn(array());

        $expr = Expr::method('getServerPath', Expr::same('/foobar'));

        $this->assertEquals(array(), $this->manager->findAssetMappings($expr));
    }

    public function testHasAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('hasBindingDescriptors')
            ->with($this->defaultExpr())
            ->willReturn(true);

        $this->assertTrue($this->manager->hasAssetMappings());
    }

    public function testHasNoAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('hasBindingDescriptors')
            ->with($this->defaultExpr())
            ->willReturn(false);

        $this->assertFalse($this->manager->hasAssetMappings());
    }

    public function testHasAssetMappingsWithExpression()
    {
        $this->discoveryManager->expects($this->once())
            ->method('hasBindingDescriptors')
            ->with($this->webPath('/path'))
            ->willReturn(true);

        $expr = Expr::method('getServerPath', Expr::same('/path'));

        $this->assertTrue($this->manager->hasAssetMappings($expr));
    }

    public function testGetRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findRootBindingDescriptors')
            ->with($this->defaultExpr())
            ->willReturn(array($this->bindingDescriptor1, $this->bindingDescriptor2));

        $expected = array(
            new AssetMapping('/path', 'target1', '/css'),
            new AssetMapping('/other/path', 'target2', '/js'),
        );

        $this->assertEquals($expected, $this->manager->getRootAssetMappings());
    }

    public function testGetNoRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findRootBindingDescriptors')
            ->with($this->defaultExpr())
            ->willReturn(array());

        $this->assertEquals(array(), $this->manager->getRootAssetMappings());
    }

    public function testFindRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findRootBindingDescriptors')
            ->with($this->webPath('/other/path'))
            ->willReturn(array($this->bindingDescriptor2));

        $expr = Expr::method('getServerPath', Expr::same('/other/path'));
        $expected = new AssetMapping('/other/path', 'target2', '/js');

        $this->assertEquals(array($expected), $this->manager->findRootAssetMappings($expr));
    }

    public function testFindNoRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findRootBindingDescriptors')
            ->with($this->webPath('/foobar'))
            ->willReturn(array());

        $expr = Expr::method('getServerPath', Expr::same('/foobar'));

        $this->assertEquals(array(), $this->manager->findRootAssetMappings($expr));
    }

    public function testHasRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('hasRootBindingDescriptors')
            ->with($this->defaultExpr())
            ->willReturn(true);

        $this->assertTrue($this->manager->hasRootAssetMappings());
    }

    public function testHasNoRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('hasRootBindingDescriptors')
            ->with($this->defaultExpr())
            ->willReturn(false);

        $this->assertFalse($this->manager->hasRootAssetMappings());
    }

    public function testHasRootAssetMappingsWithExpression()
    {
        $this->discoveryManager->expects($this->once())
            ->method('hasRootBindingDescriptors')
            ->with($this->webPath('/path'))
            ->willReturn(true);

        $expr = Expr::method('getServerPath', Expr::same('/path'));

        $this->assertTrue($this->manager->hasRootAssetMappings($expr));
    }

    private function defaultExpr()
    {
        return Expr::method('isEnabled', Expr::same(true))
            ->andMethod('getTypeName', Expr::same(DiscoveryUrlGenerator::BINDING_TYPE))
            ->andMethod('getBinding', Expr::method('getQuery', Expr::endsWith('{,/**/*}')));
    }

    private function mapping(AssetMapping $mapping)
    {
        return $this->defaultExpr()
            ->andMethod('getBinding', Expr::method('getQuery', Expr::same($mapping->getGlob().'{,/**/*}')))
            ->andMethod('getParameterValue', 'server', Expr::same($mapping->getServerName()))
            ->andMethod('getParameterValue', 'path', Expr::same($mapping->getServerPath()));
    }

    private function webPath($path)
    {
        return $this->defaultExpr()->andMethod(
            'getParameterValue',
            DiscoveryUrlGenerator::PATH_PARAMETER,
            Expr::same($path)
        );
    }
}
