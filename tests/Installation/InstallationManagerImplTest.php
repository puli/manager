<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Installation;

use PHPUnit_Framework_MockObject_MockObject;
use Puli\Manager\Api\Asset\AssetMapping;
use Puli\Manager\Api\Installation\InstallationParams;
use Puli\Manager\Api\Installer\InstallerDescriptor;
use Puli\Manager\Api\Installer\InstallerManager;
use Puli\Manager\Api\Installer\InstallerParameter;
use Puli\Manager\Api\Server\Server;
use Puli\Manager\Api\Server\ServerCollection;
use Puli\Manager\Installation\InstallationManagerImpl;
use Puli\Manager\Tests\Installation\Fixtures\TestInstaller;
use Puli\Manager\Tests\ManagerTestCase;
use Puli\Repository\Resource\Collection\ArrayResourceCollection;
use Puli\Repository\Resource\GenericResource;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallationManagerImplTest extends ManagerTestCase
{
    const INSTALLER_CLASS = 'Puli\Manager\Tests\Installation\Fixtures\TestInstaller';

    const INSTALLER_CLASS_NO_DEFAULT_CONSTRUCTOR = 'Puli\Manager\Tests\Installation\Fixtures\TestInstallerWithoutDefaultConstructor';

    const INSTALLER_CLASS_INVALID = 'Puli\Manager\Tests\Installation\Fixtures\TestInstallerInvalid';

    /**
     * @var ServerCollection
     */
    private $servers;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|InstallerManager
     */
    private $installerManager;

    /**
     * @var InstallationManagerImpl
     */
    private $manager;

    protected function setUp()
    {
        $this->initEnvironment(__DIR__.'/Fixtures/home', __DIR__.'/Fixtures/root');

        $this->servers = new ServerCollection();
        $this->installerManager = $this->getMock('Puli\Manager\Api\Installer\InstallerManager');
        $this->manager = new InstallationManagerImpl(
            $this->environment,
            $this->repo,
            $this->servers,
            $this->installerManager
        );

        TestInstaller::resetValidatedParams();
    }

    public function testPrepareInstallation()
    {
        $resources = new ArrayResourceCollection(array(
            new GenericResource('/path/css'),
            new GenericResource('/path/js'),
        ));
        $installerDescriptor = new InstallerDescriptor('rsync', self::INSTALLER_CLASS, null, array(
            new InstallerParameter('param1', InstallerParameter::REQUIRED),
            new InstallerParameter('param2', InstallerParameter::OPTIONAL, 'default1'),
            new InstallerParameter('param3', InstallerParameter::OPTIONAL, 'default2'),
        ));
        $server = new Server('example.com', 'rsync', 'ssh://example.com/public_html', '/%s', array(
            'param1' => 'custom1',
            'param3' => 'custom2',
        ));
        $mapping = new AssetMapping('/path/{css,js}', 'example.com', 'assets');

        $this->servers->add($server);

        $this->repo->expects($this->any())
            ->method('find')
            ->with('/path/{css,js}')
            ->willReturn($resources);

        $this->installerManager->expects($this->any())
            ->method('hasInstallerDescriptor')
            ->with('rsync')
            ->willReturn(true);

        $this->installerManager->expects($this->any())
            ->method('getInstallerDescriptor')
            ->with('rsync')
            ->willReturn($installerDescriptor);

        $params = new InstallationParams(
            new TestInstaller(),
            $installerDescriptor,
            $resources,
            $mapping,
            $server,
            $this->environment->getRootDirectory()
        );

        $this->assertEquals($params, $this->manager->prepareInstallation($mapping));
        $this->assertEquals($params, TestInstaller::getValidatedParams());
    }

    /**
     * @expectedException \Puli\Manager\Api\Installation\NotInstallableException
     * @expectedExceptionMessage foobar
     * @expectedExceptionCode 3
     */
    public function testFailIfInstallerNotFound()
    {
        $resources = new ArrayResourceCollection(array(
            new GenericResource('/path/css'),
            new GenericResource('/path/js'),
        ));
        $server = new Server('example.com', 'foobar', 'ssh://server/public_html');
        $mapping = new AssetMapping('/path/{css,js}', 'example.com', 'assets');

        $this->servers->add($server);

        $this->repo->expects($this->any())
            ->method('find')
            ->with('/path/{css,js}')
            ->willReturn($resources);

        $this->installerManager->expects($this->any())
            ->method('hasInstallerDescriptor')
            ->with('foobar')
            ->willReturn(false);

        $this->installerManager->expects($this->never())
            ->method('getInstallerDescriptor');

        $this->manager->prepareInstallation($mapping);
    }

    /**
     * @expectedException \Puli\Manager\Api\Installation\NotInstallableException
     * @expectedExceptionMessage /path/{css,js}
     * @expectedExceptionCode 4
     */
    public function testFailIfNoResourceMatches()
    {
        $resources = new ArrayResourceCollection();
        $installerDescriptor = new InstallerDescriptor('rsync', self::INSTALLER_CLASS, null, array(
            new InstallerParameter('param1', InstallerParameter::REQUIRED),
            new InstallerParameter('param2', InstallerParameter::OPTIONAL, 'default1'),
            new InstallerParameter('param3', InstallerParameter::OPTIONAL, 'default2'),
        ));
        $server = new Server('example.com', 'rsync', 'ssh://server/public_html', '/%s', array(
            'param1' => 'custom1',
            'param3' => 'custom2',
        ));
        $mapping = new AssetMapping('/path/{css,js}', 'example.com', 'assets');

        $this->servers->add($server);

        $this->repo->expects($this->any())
            ->method('find')
            ->with('/path/{css,js}')
            ->willReturn($resources);

        $this->installerManager->expects($this->any())
            ->method('hasInstallerDescriptor')
            ->with('rsync')
            ->willReturn(true);

        $this->installerManager->expects($this->any())
            ->method('getInstallerDescriptor')
            ->with('rsync')
            ->willReturn($installerDescriptor);

        $this->manager->prepareInstallation($mapping);
    }

    /**
     * @expectedException \Puli\Manager\Api\Installation\NotInstallableException
     * @expectedExceptionMessage foobar
     * @expectedExceptionCode 5
     */
    public function testFailIfServerNotFound()
    {
        $resources = new ArrayResourceCollection(array(
            new GenericResource('/path/css'),
            new GenericResource('/path/js'),
        ));
        $installerDescriptor = new InstallerDescriptor('rsync', self::INSTALLER_CLASS, null, array(
            new InstallerParameter('param1', InstallerParameter::REQUIRED),
            new InstallerParameter('param2', InstallerParameter::OPTIONAL, 'default1'),
            new InstallerParameter('param3', InstallerParameter::OPTIONAL, 'default2'),
        ));
        $mapping = new AssetMapping('/path/{css,js}', 'foobar', 'assets');

        $this->repo->expects($this->any())
            ->method('find')
            ->with('/path/{css,js}')
            ->willReturn($resources);

        $this->installerManager->expects($this->any())
            ->method('hasInstallerDescriptor')
            ->with('rsync')
            ->willReturn(true);

        $this->installerManager->expects($this->any())
            ->method('getInstallerDescriptor')
            ->with('rsync')
            ->willReturn($installerDescriptor);

        $this->manager->prepareInstallation($mapping);
    }

    /**
     * @expectedException \Puli\Manager\Api\Installation\NotInstallableException
     * @expectedExceptionMessage Puli\Manager\Tests\Installation\Foobar
     * @expectedExceptionCode 6
     */
    public function testFailIfInstallerClassNotFound()
    {
        $resources = new ArrayResourceCollection(array(
            new GenericResource('/path/css'),
            new GenericResource('/path/js'),
        ));
        $installerDescriptor = new InstallerDescriptor('rsync', __NAMESPACE__.'\Foobar', null, array(
            new InstallerParameter('param1', InstallerParameter::REQUIRED),
            new InstallerParameter('param2', InstallerParameter::OPTIONAL, 'default1'),
            new InstallerParameter('param3', InstallerParameter::OPTIONAL, 'default2'),
        ));
        $server = new Server('example.com', 'rsync', 'ssh://server/public_html', '/%s', array(
            'param1' => 'custom1',
            'param3' => 'custom2',
        ));
        $mapping = new AssetMapping('/path/{css,js}', 'example.com', 'assets');

        $this->servers->add($server);

        $this->repo->expects($this->any())
            ->method('find')
            ->with('/path/{css,js}')
            ->willReturn($resources);

        $this->installerManager->expects($this->any())
            ->method('hasInstallerDescriptor')
            ->with('rsync')
            ->willReturn(true);

        $this->installerManager->expects($this->any())
            ->method('getInstallerDescriptor')
            ->with('rsync')
            ->willReturn($installerDescriptor);

        $this->manager->prepareInstallation($mapping);
    }

    /**
     * @expectedException \Puli\Manager\Api\Installation\NotInstallableException
     * @expectedExceptionMessage Puli\Manager\Tests\Installation\Fixtures\TestInstallerWithoutDefaultConstructor
     * @expectedExceptionCode 7
     */
    public function testFailIfInstallerClassNoDefaultConstructor()
    {
        $resources = new ArrayResourceCollection(array(
            new GenericResource('/path/css'),
            new GenericResource('/path/js'),
        ));
        $installerDescriptor = new InstallerDescriptor('rsync', self::INSTALLER_CLASS_NO_DEFAULT_CONSTRUCTOR, null, array(
            new InstallerParameter('param1', InstallerParameter::REQUIRED),
            new InstallerParameter('param2', InstallerParameter::OPTIONAL, 'default1'),
            new InstallerParameter('param3', InstallerParameter::OPTIONAL, 'default2'),
        ));
        $server = new Server('example.com', 'rsync', 'ssh://server/public_html', '/%s', array(
            'param1' => 'custom1',
            'param3' => 'custom2',
        ));
        $mapping = new AssetMapping('/path/{css,js}', 'example.com', 'assets');

        $this->servers->add($server);

        $this->repo->expects($this->any())
            ->method('find')
            ->with('/path/{css,js}')
            ->willReturn($resources);

        $this->installerManager->expects($this->any())
            ->method('hasInstallerDescriptor')
            ->with('rsync')
            ->willReturn(true);

        $this->installerManager->expects($this->any())
            ->method('getInstallerDescriptor')
            ->with('rsync')
            ->willReturn($installerDescriptor);

        $this->manager->prepareInstallation($mapping);
    }

    /**
     * @expectedException \Puli\Manager\Api\Installation\NotInstallableException
     * @expectedExceptionMessage Puli\Manager\Tests\Installation\Fixtures\TestInstallerInvalid
     * @expectedExceptionCode 8
     */
    public function testFailIfInstallerClassInvalid()
    {
        $resources = new ArrayResourceCollection(array(
            new GenericResource('/path/css'),
            new GenericResource('/path/js'),
        ));
        $installerDescriptor = new InstallerDescriptor('rsync', self::INSTALLER_CLASS_INVALID, null, array(
            new InstallerParameter('param1', InstallerParameter::REQUIRED),
            new InstallerParameter('param2', InstallerParameter::OPTIONAL, 'default1'),
            new InstallerParameter('param3', InstallerParameter::OPTIONAL, 'default2'),
        ));
        $server = new Server('example.com', 'rsync', 'ssh://server/public_html', '/%s', array(
            'param1' => 'custom1',
            'param3' => 'custom2',
        ));
        $mapping = new AssetMapping('/path/{css,js}', 'example.com', 'assets');

        $this->servers->add($server);

        $this->repo->expects($this->any())
            ->method('find')
            ->with('/path/{css,js}')
            ->willReturn($resources);

        $this->installerManager->expects($this->any())
            ->method('hasInstallerDescriptor')
            ->with('rsync')
            ->willReturn(true);

        $this->installerManager->expects($this->any())
            ->method('getInstallerDescriptor')
            ->with('rsync')
            ->willReturn($installerDescriptor);

        $this->manager->prepareInstallation($mapping);
    }

    public function testInstallResource()
    {
        $resources = new ArrayResourceCollection(array(
            $first = new GenericResource('/path/css'),
            $second = new GenericResource('/path/js'),
        ));

        $installer = $this->getMock('Puli\Manager\Api\Installer\ResourceInstaller');
        $installerDescriptor = new InstallerDescriptor('symlink', get_class($installer));
        $server = new Server('server', 'rsync', 'ssh://server/public_html');
        $mapping = new AssetMapping('/path/{css,js}', 'server', 'assets');

        $params = new InstallationParams(
            $installer,
            $installerDescriptor,
            $resources,
            $mapping,
            $server,
            $this->environment->getRootDirectory()
        );

        $installer->expects($this->at(0))
            ->method('validateParams')
            ->with($params);
        $installer->expects($this->at(1))
            ->method('installResource')
            ->with($first, $params);

        $this->manager->installResource($first, $params);
    }
}
