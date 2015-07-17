<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Installer;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Asset\AssetMapping;
use Puli\Manager\Api\Installation\InstallationParams;
use Puli\Manager\Api\Installer\InstallerDescriptor;
use Puli\Manager\Api\Server\Server;
use Puli\Manager\Installer\CopyInstaller;
use Puli\Repository\Resource\Collection\ArrayResourceCollection;
use Puli\Repository\Resource\DirectoryResource;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CopyInstallerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var CopyInstaller
     */
    private $installer;

    /**
     * @var InstallerDescriptor
     */
    private $installerDescriptor;

    protected function setUp()
    {
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-web-plugin/CopyInstallerTest'.rand(10000, 99999), 0777, true)) {
        }

        $this->installer = new CopyInstaller();
        $this->installerDescriptor = new InstallerDescriptor('copy', get_class($this->installer));
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testInstallResource()
    {
        $mapping = new AssetMapping('/app/public', 'localhost', '/');
        $server = new Server('localhost', 'copy', 'public_html');

        $resource = new DirectoryResource(__DIR__.'/Fixtures', '/app/public');

        $params = new InstallationParams(
            $this->installer,
            $this->installerDescriptor,
            new ArrayResourceCollection(array($resource)),
            $mapping,
            $server,
            $this->tempDir
        );

        $this->installer->installResource($resource, $params);

        $this->assertFileExists($this->tempDir.'/public_html');
        $this->assertFileExists($this->tempDir.'/public_html/css');
        $this->assertFileExists($this->tempDir.'/public_html/css/style.css');
        $this->assertFileExists($this->tempDir.'/public_html/js');
        $this->assertFileExists($this->tempDir.'/public_html/js/script.js');

        $this->assertFalse(is_link($this->tempDir.'/public_html'));
        $this->assertFalse(is_link($this->tempDir.'/public_html/css'));
        $this->assertFalse(is_link($this->tempDir.'/public_html/css/style.css'));
        $this->assertFalse(is_link($this->tempDir.'/public_html/js'));
        $this->assertFalse(is_link($this->tempDir.'/public_html/js/script.js'));
    }

    public function testInstallResourceWithBasePath()
    {
        $mapping = new AssetMapping('/app/public/{css,js}', 'localhost', '/');
        $server = new Server('localhost', 'symlink', 'public_html');

        $resource = new DirectoryResource(__DIR__.'/Fixtures/css', '/app/public/css');

        $params = new InstallationParams(
            $this->installer,
            $this->installerDescriptor,
            new ArrayResourceCollection(array($resource)),
            $mapping,
            $server,
            $this->tempDir
        );

        $this->installer->installResource($resource, $params);

        $this->assertFileExists($this->tempDir.'/public_html');
        $this->assertFileExists($this->tempDir.'/public_html/css');
        $this->assertFileExists($this->tempDir.'/public_html/css/style.css');
        $this->assertFileNotExists($this->tempDir.'/public_html/js');
        $this->assertFileNotExists($this->tempDir.'/public_html/js/script.js');

        $this->assertFalse(is_link($this->tempDir.'/public_html'));
        $this->assertFalse(is_link($this->tempDir.'/public_html/css'));
        $this->assertFalse(is_link($this->tempDir.'/public_html/css/style.css'));
    }
}
