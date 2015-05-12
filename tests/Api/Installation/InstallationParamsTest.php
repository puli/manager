<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Installation;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Asset\AssetMapping;
use Puli\Manager\Api\Installation\InstallationParams;
use Puli\Manager\Api\Installer\InstallerDescriptor;
use Puli\Manager\Api\Installer\InstallerParameter;
use Puli\Manager\Api\Server\Server;
use Puli\Manager\Tests\Installation\Fixtures\TestInstaller;
use Puli\Repository\Resource\Collection\ArrayResourceCollection;
use Puli\Repository\Resource\GenericResource;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallationParamsTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $installer = new TestInstaller();
        $descriptor = new InstallerDescriptor('test', get_class($installer), null, array(
            new InstallerParameter('param1', InstallerParameter::OPTIONAL, 'default1'),
            new InstallerParameter('param2', InstallerParameter::OPTIONAL, 'default2'),
        ));
        $resources = new ArrayResourceCollection();
        $mapping = new AssetMapping('/path/to/{css,js}', 'localhost', '/demo');
        $server = new Server('localhost', 'symlink', 'public_html', '/%s', array(
            'param2' => 'custom',
        ));

        $params = new InstallationParams(
            $installer,
            $descriptor,
            $resources,
            $mapping,
            $server,
            '/root'
        );

        $this->assertSame($installer, $params->getInstaller());
        $this->assertSame($descriptor, $params->getInstallerDescriptor());
        $this->assertSame($resources, $params->getResources());
        $this->assertSame('/root', $params->getRootDirectory());
        $this->assertSame('/path/to', $params->getBasePath());
        $this->assertSame('public_html', $params->getDocumentRoot());
        $this->assertSame('/demo', $params->getServerPath());
        $this->assertSame(array(
            'param1' => 'default1',
            'param2' => 'custom',
        ), $params->getParameterValues());
    }

    public function testCreateWithStaticGlob()
    {
        $installer = new TestInstaller();
        $descriptor = new InstallerDescriptor('test', get_class($installer), null, array(
            new InstallerParameter('param1', InstallerParameter::OPTIONAL, 'default1'),
            new InstallerParameter('param2', InstallerParameter::OPTIONAL, 'default2'),
        ));
        $resources = new ArrayResourceCollection();
        $mapping = new AssetMapping('/path/to/css', 'localhost', '/demo');
        $server = new Server('localhost', 'symlink', 'public_html', '/%s', array(
            'param2' => 'custom',
        ));

        $params = new InstallationParams(
            $installer,
            $descriptor,
            $resources,
            $mapping,
            $server,
            '/root'
        );

        $this->assertSame('/path/to/css', $params->getBasePath());
    }

    /**
     * @expectedException \Puli\Manager\Api\Installation\NotInstallableException
     * @expectedExceptionMessage foobar
     * @expectedExceptionCode 1
     */
    public function testFailIfMissingRequiredParameters()
    {
        $installer = new TestInstaller();
        $descriptor = new InstallerDescriptor('test', get_class($installer), null, array(
            new InstallerParameter('foobar', InstallerParameter::REQUIRED),
        ));
        $resources = new ArrayResourceCollection();
        $mapping = new AssetMapping('/path/to/{css,js}', 'localhost', '/demo');
        $server = new Server('localhost', 'symlink', 'public_html');

        new InstallationParams(
            $installer,
            $descriptor,
            $resources,
            $mapping,
            $server,
            '/root'
        );
    }

    /**
     * @expectedException \Puli\Manager\Api\Installation\NotInstallableException
     * @expectedExceptionMessage foobar
     * @expectedExceptionCode 2
     */
    public function testFailIfUnknownParameter()
    {
        $installer = new TestInstaller();
        $descriptor = new InstallerDescriptor('test', get_class($installer));
        $resources = new ArrayResourceCollection();
        $mapping = new AssetMapping('/path/to/{css,js}', 'localhost', '/demo');
        $server = new Server('localhost', 'symlink', 'public_html', '/%s', array(
            'foobar' => 'value',
        ));

        new InstallationParams(
            $installer,
            $descriptor,
            $resources,
            $mapping,
            $server,
            '/root'
        );
    }

    public function testGetServerPathForResource()
    {
        $installer = new TestInstaller();
        $descriptor = new InstallerDescriptor('test', get_class($installer));
        $resources = new ArrayResourceCollection(array(
            $resource1 = new GenericResource('/acme/blog/public/css'),
            $resource2 = new GenericResource('/acme/blog/public/js'),
        ));
        $mapping = new AssetMapping('/acme/blog/public/{css,js}', 'localhost', '/blog');
        $server = new Server('localhost', 'symlink', 'public_html');

        $params = new InstallationParams(
            $installer,
            $descriptor,
            $resources,
            $mapping,
            $server,
            '/root'
        );

        $this->assertSame('/blog/css', $params->getServerPathForResource($resource1));
        $this->assertSame('/blog/js', $params->getServerPathForResource($resource2));
    }

    public function testGetServerPathForResourceSamePathAsBasePath()
    {
        $installer = new TestInstaller();
        $descriptor = new InstallerDescriptor('test', get_class($installer));
        $resources = new ArrayResourceCollection(array(
            $resource1 = new GenericResource('/acme/blog/public'),
        ));
        $mapping = new AssetMapping('/acme/blog/public', 'localhost', '/blog');
        $server = new Server('localhost', 'symlink', 'public_html');

        $params = new InstallationParams(
            $installer,
            $descriptor,
            $resources,
            $mapping,
            $server,
            '/root'
        );

        $this->assertSame('/blog', $params->getServerPathForResource($resource1));
    }

    public function testGetServerPathForResourceInRoot()
    {
        $installer = new TestInstaller();
        $descriptor = new InstallerDescriptor('test', get_class($installer));
        $resources = new ArrayResourceCollection(array(
            $resource1 = new GenericResource('/acme/blog/public'),
        ));
        $mapping = new AssetMapping('/acme/blog/public', 'localhost', '/');
        $server = new Server('localhost', 'symlink', 'public_html');

        $params = new InstallationParams(
            $installer,
            $descriptor,
            $resources,
            $mapping,
            $server,
            '/root'
        );

        $this->assertSame('/', $params->getServerPathForResource($resource1));
    }
}
