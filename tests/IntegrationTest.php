<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests;

use Puli\PackageManager\PackageManager;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var string
     */
    private $tempHome;

    protected function setUp()
    {
        while (false === mkdir($this->tempDir = sys_get_temp_dir().'/puli-manager/PackageManagerTest_temp'.rand(10000, 99999), 0777, true)) {}
        while (false === mkdir($this->tempHome = sys_get_temp_dir().'/puli-manager/PackageManagerTest_home'.rand(10000, 99999), 0777, true)) {}

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures/real-root-package', $this->tempDir);

        putenv('PULI_HOME='.$this->tempHome);
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
        $filesystem->remove($this->tempHome);

        // Unset env variables
        putenv('PULI_HOME');
    }

    public function testCreatePackageManager()
    {
        $manager = PackageManager::createPackageManager($this->tempDir);

        $this->assertInstanceOf('Puli\PackageManager\PackageManager', $manager);

        $packages = $manager->getPackages();

        $this->assertCount(3, $packages);
        $this->assertSame('real-root', $packages['real-root']->getName());
        $this->assertSame('package1', $packages['package1']->getName());
        $this->assertSame('package2', $packages['package2']->getName());
    }

}
