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
use Puli\Manager\Api\Asset\AssetManager;
use Puli\Manager\Api\Asset\AssetMapping;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingState;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Server\Server;
use Puli\Manager\Api\Server\ServerCollection;
use Puli\Manager\Asset\DiscoveryAssetManager;
use Puli\UrlGenerator\DiscoveryUrlGenerator;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Expression\Expr;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DiscoveryAssetManagerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PHPUnit_Framework_MockObject_MockObject|DiscoveryManager
     */
    private $discoveryManager;

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
     * @var Package
     */
    private $package;

    /**
     * @var RootPackage
     */
    private $rootPackage;

    /**
     * @var BindingTypeDescriptor
     */
    private $bindingType;

    /**
     * @var BindingDescriptor
     */
    private $binding1;

    /**
     * @var BindingDescriptor
     */
    private $binding2;

    /**
     * @var DiscoveryAssetManager
     */
    private $manager;

    protected function setUp()
    {
        $this->discoveryManager = $this->getMock('Puli\Manager\Api\Discovery\DiscoveryManager');
        $this->server1 = new Server('target1', 'symlink', 'public_html');
        $this->server2 = new Server('target2', 'rsync', 'ssh://server');
        $this->servers = new ServerCollection(array($this->server1, $this->server2));
        $this->manager = new DiscoveryAssetManager($this->discoveryManager, $this->servers);
        $this->package = new Package(new PackageFile('vendor/package'), '/path');
        $this->rootPackage = new RootPackage(new RootPackageFile('vendor/root'), '/path');
        $this->bindingType = new BindingTypeDescriptor(DiscoveryUrlGenerator::BINDING_TYPE);
        $this->binding1 = new BindingDescriptor(
            '/path{,/**/*}',
            DiscoveryUrlGenerator::BINDING_TYPE,
            array(
                DiscoveryUrlGenerator::SERVER_PARAMETER => 'target1',
                DiscoveryUrlGenerator::PATH_PARAMETER => '/css',
            )
        );
        $this->binding2 = new BindingDescriptor(
            '/other/path{,/**/*}',
            DiscoveryUrlGenerator::BINDING_TYPE,
            array(
                DiscoveryUrlGenerator::SERVER_PARAMETER => 'target2',
                DiscoveryUrlGenerator::PATH_PARAMETER => '/js',
            )
        );
    }

    public function testAddRootAssetMapping()
    {
        $uuid = Uuid::uuid4();

        $expectedBinding = new BindingDescriptor(
            '/path{,/**/*}',
            DiscoveryUrlGenerator::BINDING_TYPE,
            array(
                DiscoveryUrlGenerator::SERVER_PARAMETER => 'target1',
                DiscoveryUrlGenerator::PATH_PARAMETER => '/css',
            ),
            'glob',
            $uuid
        );

        $this->discoveryManager->expects($this->once())
            ->method('hasBindings')
            ->with($this->uuid($uuid))
            ->willReturn(false);

        $this->discoveryManager->expects($this->once())
            ->method('addRootBinding')
            ->with($expectedBinding);

        $this->manager->addRootAssetMapping(new AssetMapping('/path', 'target1', '/css', $uuid));
    }

    /**
     * @expectedException \Puli\Manager\Api\Server\NoSuchServerException
     * @expectedExceptionMessage foobar
     */
    public function testAddRootAssetMappingFailsIfServerNotFound()
    {
        $this->discoveryManager->expects($this->never())
            ->method('hasBindings');

        $this->discoveryManager->expects($this->never())
            ->method('addRootBinding');

        $this->manager->addRootAssetMapping(new AssetMapping('/path', 'foobar', '/css'));
    }

    public function testAddRootAssetMappingDoesNotFailIfServerNotFoundAndIgnoreServerNotFound()
    {
        $uuid = Uuid::uuid4();

        $expectedBinding = new BindingDescriptor(
            '/path{,/**/*}',
            DiscoveryUrlGenerator::BINDING_TYPE,
            array(
                DiscoveryUrlGenerator::SERVER_PARAMETER => 'foobar',
                DiscoveryUrlGenerator::PATH_PARAMETER => '/css',
            ),
            'glob',
            $uuid
        );

        $this->discoveryManager->expects($this->once())
            ->method('hasBindings')
            ->with($this->uuid($uuid))
            ->willReturn(false);

        $this->discoveryManager->expects($this->once())
            ->method('addRootBinding')
            ->with($expectedBinding);

        $this->manager->addRootAssetMapping(new AssetMapping('/path', 'foobar', '/css', $uuid), AssetManager::IGNORE_SERVER_NOT_FOUND);
    }

    /**
     * @expectedException \Puli\Manager\Api\Asset\DuplicateAssetMappingException
     * @expectedExceptionMessage The asset mapping "76e83c4e-2c0d-44de-b1cb-57a3e0d925a1" exists already.
     */
    public function testAddRootAssetMappingFailsIfUuidExistsAlready()
    {
        $uuid = Uuid::fromString('76e83c4e-2c0d-44de-b1cb-57a3e0d925a1');

        $this->discoveryManager->expects($this->once())
            ->method('hasBindings')
            ->with($this->uuid($uuid))
            ->willReturn(true);

        $this->discoveryManager->expects($this->never())
            ->method('addRootBinding');

        $this->manager->addRootAssetMapping(new AssetMapping('/path', 'target1', '/css', $uuid));
    }

    public function testAddRootAssetMappingDoesNotFailIfUuidExistsAlreadyAndOverride()
    {
        $uuid = Uuid::fromString('76e83c4e-2c0d-44de-b1cb-57a3e0d925a1');

        $expectedBinding = new BindingDescriptor(
            '/path{,/**/*}',
            DiscoveryUrlGenerator::BINDING_TYPE,
            array(
                DiscoveryUrlGenerator::SERVER_PARAMETER => 'target1',
                DiscoveryUrlGenerator::PATH_PARAMETER => '/css',
            ),
            'glob',
            $uuid
        );

        $this->discoveryManager->expects($this->never())
            ->method('hasBindings');

        $this->discoveryManager->expects($this->once())
            ->method('addRootBinding')
            ->with($expectedBinding, DiscoveryManager::OVERRIDE);

        $this->manager->addRootAssetMapping(new AssetMapping('/path', 'target1', '/css', $uuid), AssetManager::OVERRIDE);
    }

    public function testRemoveRootAssetMapping()
    {
        $uuid = $this->binding1->getUuid();

        $this->bindingType->load($this->rootPackage);
        $this->binding1->load($this->rootPackage, $this->bindingType);

        $this->discoveryManager->expects($this->at(0))
            ->method('removeRootBindings')
            ->with($this->uuid($uuid));

        $this->manager->removeRootAssetMapping($uuid);
    }

    public function testRemoveRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('removeRootBindings')
            ->with($this->defaultExpr()->andKey(
                BindingDescriptor::PARAMETER_VALUES,
                Expr::key(DiscoveryUrlGenerator::SERVER_PARAMETER, Expr::same('target1'))
            ));

        $this->manager->removeRootAssetMappings(Expr::same('target1', AssetMapping::SERVER_NAME));
    }

    public function testClearRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('removeRootBindings')
            ->with($this->defaultExpr());

        $this->manager->clearRootAssetMappings();
    }

    public function testGetAssetMapping()
    {
        $uuid = $this->binding1->getUuid();

        $this->discoveryManager->expects($this->at(0))
            ->method('findBindings')
            ->with($this->uuid($uuid))
            ->willReturn(array($this->binding1));

        $expected = new AssetMapping('/path', 'target1', '/css', $uuid);

        $this->assertEquals($expected, $this->manager->getAssetMapping($uuid));
    }

    /**
     * @expectedException \Puli\Manager\Api\Asset\NoSuchAssetMappingException
     */
    public function testGetAssetMappingFailsIfNotFound()
    {
        $uuid = Uuid::uuid4();

        $this->discoveryManager->expects($this->at(0))
            ->method('findBindings')
            ->with($this->uuid($uuid))
            ->willReturn(array());

        $this->manager->getAssetMapping($uuid);
    }

    public function testGetAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findBindings')
            ->with($this->defaultExpr())
            ->willReturn(array($this->binding1, $this->binding2));

        $expected = array(
            new AssetMapping('/path', 'target1', '/css', $this->binding1->getUuid()),
            new AssetMapping('/other/path', 'target2', '/js', $this->binding2->getUuid()),
        );

        $this->assertEquals($expected, $this->manager->getAssetMappings());
    }

    public function testGetNoAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findBindings')
            ->with($this->defaultExpr())
            ->willReturn(array());

        $this->assertEquals(array(), $this->manager->getAssetMappings());
    }

    public function testFindAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findBindings')
            ->with($this->webPath('/other/path'))
            ->willReturn(array($this->binding2));

        $expr = Expr::same('/other/path', AssetMapping::SERVER_PATH);
        $expected = new AssetMapping('/other/path', 'target2', '/js', $this->binding2->getUuid());

        $this->assertEquals(array($expected), $this->manager->findAssetMappings($expr));
    }

    public function testFindNoAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findBindings')
            ->with($this->webPath('/foobar'))
            ->willReturn(array());

        $expr = Expr::same('/foobar', AssetMapping::SERVER_PATH);

        $this->assertEquals(array(), $this->manager->findAssetMappings($expr));
    }

    public function testHasAssetMapping()
    {
        $uuid = Uuid::uuid4();

        $this->discoveryManager->expects($this->once())
            ->method('hasBindings')
            ->with($this->uuid($uuid))
            ->willReturn(true);

        $this->assertTrue($this->manager->hasAssetMapping($uuid));
    }

    public function testNotHasAssetMapping()
    {
        $uuid = Uuid::uuid4();

        $this->discoveryManager->expects($this->once())
            ->method('hasBindings')
            ->with($this->uuid($uuid))
            ->willReturn(false);

        $this->assertFalse($this->manager->hasAssetMapping($uuid));
    }

    public function testHasAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('hasBindings')
            ->with($this->defaultExpr())
            ->willReturn(true);

        $this->assertTrue($this->manager->hasAssetMappings());
    }

    public function testHasNoAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('hasBindings')
            ->with($this->defaultExpr())
            ->willReturn(false);

        $this->assertFalse($this->manager->hasAssetMappings());
    }

    public function testHasAssetMappingsWithExpression()
    {
        $this->discoveryManager->expects($this->once())
            ->method('hasBindings')
            ->with($this->webPath('/path'))
            ->willReturn(true);

        $expr = Expr::same('/path', AssetMapping::SERVER_PATH);

        $this->assertTrue($this->manager->hasAssetMappings($expr));
    }

    public function testGetRootAssetMapping()
    {
        $uuid = $this->binding1->getUuid();

        $this->discoveryManager->expects($this->at(0))
            ->method('findRootBindings')
            ->with($this->uuid($uuid))
            ->willReturn(array($this->binding1));

        $expected = new AssetMapping('/path', 'target1', '/css', $uuid);

        $this->assertEquals($expected, $this->manager->getRootAssetMapping($uuid));
    }

    /**
     * @expectedException \Puli\Manager\Api\Asset\NoSuchAssetMappingException
     */
    public function testGetRootAssetMappingFailsIfNotFound()
    {
        $uuid = Uuid::uuid4();

        $this->discoveryManager->expects($this->at(0))
            ->method('findRootBindings')
            ->with($this->uuid($uuid))
            ->willReturn(array());

        $this->manager->getRootAssetMapping($uuid);
    }

    public function testGetRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findRootBindings')
            ->with($this->defaultExpr())
            ->willReturn(array($this->binding1, $this->binding2));

        $expected = array(
            new AssetMapping('/path', 'target1', '/css', $this->binding1->getUuid()),
            new AssetMapping('/other/path', 'target2', '/js', $this->binding2->getUuid()),
        );

        $this->assertEquals($expected, $this->manager->getRootAssetMappings());
    }

    public function testGetNoRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findRootBindings')
            ->with($this->defaultExpr())
            ->willReturn(array());

        $this->assertEquals(array(), $this->manager->getRootAssetMappings());
    }

    public function testFindRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findRootBindings')
            ->with($this->webPath('/other/path'))
            ->willReturn(array($this->binding2));

        $expr = Expr::same('/other/path', AssetMapping::SERVER_PATH);
        $expected = new AssetMapping('/other/path', 'target2', '/js', $this->binding2->getUuid());

        $this->assertEquals(array($expected), $this->manager->findRootAssetMappings($expr));
    }

    public function testFindNoRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('findRootBindings')
            ->with($this->webPath('/foobar'))
            ->willReturn(array());

        $expr = Expr::same('/foobar', AssetMapping::SERVER_PATH);

        $this->assertEquals(array(), $this->manager->findRootAssetMappings($expr));
    }

    public function testHasRootAssetMapping()
    {
        $uuid = Uuid::uuid4();

        $this->discoveryManager->expects($this->once())
            ->method('hasRootBindings')
            ->with($this->uuid($uuid))
            ->willReturn(true);

        $this->assertTrue($this->manager->hasRootAssetMapping($uuid));
    }

    public function testNotHasRootAssetMapping()
    {
        $uuid = Uuid::uuid4();

        $this->discoveryManager->expects($this->once())
            ->method('hasRootBindings')
            ->with($this->uuid($uuid))
            ->willReturn(false);

        $this->assertFalse($this->manager->hasRootAssetMapping($uuid));
    }

    public function testHasRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('hasRootBindings')
            ->with($this->defaultExpr())
            ->willReturn(true);

        $this->assertTrue($this->manager->hasRootAssetMappings());
    }

    public function testHasNoRootAssetMappings()
    {
        $this->discoveryManager->expects($this->once())
            ->method('hasRootBindings')
            ->with($this->defaultExpr())
            ->willReturn(false);

        $this->assertFalse($this->manager->hasRootAssetMappings());
    }

    public function testHasRootAssetMappingsWithExpression()
    {
        $this->discoveryManager->expects($this->once())
            ->method('hasRootBindings')
            ->with($this->webPath('/path'))
            ->willReturn(true);

        $expr = Expr::same('/path', AssetMapping::SERVER_PATH);

        $this->assertTrue($this->manager->hasRootAssetMappings($expr));
    }

    private function defaultExpr()
    {
        return Expr::same(BindingState::ENABLED, BindingDescriptor::STATE)
            ->andSame(DiscoveryUrlGenerator::BINDING_TYPE, BindingDescriptor::TYPE_NAME)
            ->andEndsWith('{,/**/*}', BindingDescriptor::QUERY);
    }

    private function uuid(Uuid $uuid)
    {
        return $this->defaultExpr()->andSame($uuid->toString(), BindingDescriptor::UUID);
    }

    private function webPath($path)
    {
        return $this->defaultExpr()->andKey(
            BindingDescriptor::PARAMETER_VALUES,
            Expr::key(
                DiscoveryUrlGenerator::PATH_PARAMETER,
                Expr::same($path)
            )
        );
    }
}
