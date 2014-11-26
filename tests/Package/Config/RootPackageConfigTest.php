<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\Config;

use Puli\RepositoryManager\Config\GlobalConfig;
use Puli\RepositoryManager\Package\Config\RootPackageConfig;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageConfigTest extends \PHPUnit_Framework_TestCase
{
    const GLOBAL_PLUGIN = 'Puli\RepositoryManager\Tests\Config\Fixtures\TestPlugin';

    const LOCAL_PLUGIN = 'Puli\RepositoryManager\Tests\Config\Fixtures\OtherPlugin';

    /**
     * @var GlobalConfig
     */
    private $globalConfig;

    /**
     * @var RootPackageConfig
     */
    private $config;

    protected function setUp()
    {
        $this->globalConfig = new GlobalConfig();
        $this->config = new RootPackageConfig($this->globalConfig);
    }

    public function testNoLocalInstallFile()
    {
        $this->globalConfig->setInstallFile('global');

        $this->assertSame('global', $this->config->getInstallFile());
        $this->assertNull($this->config->getInstallFile(false));
    }

    public function testLocalInstallFile()
    {
        $this->globalConfig->setInstallFile('global');
        $this->config->setInstallFile('local');

        $this->assertSame('local', $this->config->getInstallFile());
        $this->assertSame('local', $this->config->getInstallFile(false));
    }

    public function testGetInstallFileIfSameValues()
    {
        $this->globalConfig->setInstallFile('global');

        // Deliberately set to same value
        $this->config->setInstallFile('global');

        $this->assertSame('global', $this->config->getInstallFile());
        $this->assertSame('global', $this->config->getInstallFile(false));
    }

    public function testResetInstallFile()
    {
        $this->globalConfig->setInstallFile('global');
        $this->config->setInstallFile('local');
        $this->config->resetInstallFile();

        $this->assertSame('global', $this->config->getInstallFile());
        $this->assertNull($this->config->getInstallFile(false));
    }

    public function testNoLocalGeneratedResourceRepository()
    {
        $this->globalConfig->setGeneratedResourceRepository('global');

        $this->assertSame('global', $this->config->getGeneratedResourceRepository());
        $this->assertNull($this->config->getGeneratedResourceRepository(false));
    }

    public function testLocalGeneratedResourceRepository()
    {
        $this->globalConfig->setGeneratedResourceRepository('global');
        $this->config->setGeneratedResourceRepository('local');

        $this->assertSame('local', $this->config->getGeneratedResourceRepository());
        $this->assertSame('local', $this->config->getGeneratedResourceRepository(false));
    }

    public function testGetGeneratedResourceRepositoryIfSameValues()
    {
        $this->globalConfig->setGeneratedResourceRepository('global');

        // Deliberately set to same value
        $this->config->setGeneratedResourceRepository('global');

        $this->assertSame('global', $this->config->getGeneratedResourceRepository());
        $this->assertSame('global', $this->config->getGeneratedResourceRepository(false));
    }

    public function testResetGeneratedResourceRepository()
    {
        $this->globalConfig->setGeneratedResourceRepository('global');
        $this->config->setGeneratedResourceRepository('local');
        $this->config->resetGeneratedResourceRepository();

        $this->assertSame('global', $this->config->getGeneratedResourceRepository());
        $this->assertNull($this->config->getGeneratedResourceRepository(false));
    }

    public function testNoLocalResourceRepositoryCache()
    {
        $this->globalConfig->setResourceRepositoryCache('global');

        $this->assertSame('global', $this->config->getResourceRepositoryCache());
        $this->assertNull($this->config->getResourceRepositoryCache(false));
    }

    public function testLocalResourceRepositoryCache()
    {
        $this->globalConfig->setResourceRepositoryCache('global');
        $this->config->setResourceRepositoryCache('local');

        $this->assertSame('local', $this->config->getResourceRepositoryCache());
        $this->assertSame('local', $this->config->getResourceRepositoryCache(false));
    }

    public function testGetResourceRepositoryCacheIfSameValues()
    {
        $this->globalConfig->setResourceRepositoryCache('global');

        // Deliberately set to same value
        $this->config->setResourceRepositoryCache('global');

        $this->assertSame('global', $this->config->getResourceRepositoryCache());
        $this->assertSame('global', $this->config->getResourceRepositoryCache(false));
    }

    public function testResetResourceRepositoryCache()
    {
        $this->globalConfig->setResourceRepositoryCache('global');
        $this->config->setResourceRepositoryCache('local');
        $this->config->resetResourceRepositoryCache();

        $this->assertSame('global', $this->config->getResourceRepositoryCache());
        $this->assertNull($this->config->getResourceRepositoryCache(false));
    }

    public function testNoLocalPluginClasses()
    {
        $this->globalConfig->addPluginClass(self::GLOBAL_PLUGIN);

        $this->assertSame(array(self::GLOBAL_PLUGIN), $this->config->getPluginClasses());
        $this->assertSame(array(), $this->config->getPluginClasses(false));
    }

    public function testLocalPluginClasses()
    {
        $this->globalConfig->addPluginClass(self::GLOBAL_PLUGIN);
        $this->config->addPluginClass(self::LOCAL_PLUGIN);

        $this->assertSame(array(self::GLOBAL_PLUGIN, self::LOCAL_PLUGIN), $this->config->getPluginClasses());
        $this->assertSame(array(self::LOCAL_PLUGIN), $this->config->getPluginClasses(false));
    }

    public function testRemoveLocalPluginClass()
    {
        $this->globalConfig->addPluginClass(self::GLOBAL_PLUGIN);
        $this->config->addPluginClass(self::LOCAL_PLUGIN);
        $this->config->removePluginClass(self::LOCAL_PLUGIN);

        $this->assertSame(array(self::GLOBAL_PLUGIN), $this->config->getPluginClasses());
        $this->assertSame(array(), $this->config->getPluginClasses(false));
    }

    public function testGetPluginClassesIfSameValues()
    {
        $this->globalConfig->addPluginClass(self::GLOBAL_PLUGIN);

        // Deliberately set to same value
        $this->config->addPluginClass(self::GLOBAL_PLUGIN);

        $this->assertSame(array(self::GLOBAL_PLUGIN), $this->config->getPluginClasses());
        $this->assertSame(array(self::GLOBAL_PLUGIN), $this->config->getPluginClasses(false));
    }

    public function testResetPluginClasses()
    {
        $this->globalConfig->addPluginClass(self::GLOBAL_PLUGIN);
        $this->config->addPluginClass(self::LOCAL_PLUGIN);
        $this->config->resetPluginClasses();

        $this->assertSame(array(self::GLOBAL_PLUGIN), $this->config->getPluginClasses());
        $this->assertSame(array(), $this->config->getPluginClasses(false));
    }

    public function testSetPluginClasses()
    {
        $this->config->setPluginClasses(array(self::GLOBAL_PLUGIN, self::LOCAL_PLUGIN));

        $this->assertSame(array(self::GLOBAL_PLUGIN, self::LOCAL_PLUGIN), $this->config->getPluginClasses());
        $this->assertSame(array(self::GLOBAL_PLUGIN, self::LOCAL_PLUGIN), $this->config->getPluginClasses(false));
    }

    public function testHasPluginClass()
    {
        $this->globalConfig->addPluginClass(self::GLOBAL_PLUGIN);
        $this->config->addPluginClass(self::LOCAL_PLUGIN);

        $this->assertTrue($this->config->hasPluginClass(self::GLOBAL_PLUGIN, true));
        $this->assertTrue($this->config->hasPluginClass(self::LOCAL_PLUGIN, true));
        $this->assertFalse($this->config->hasPluginClass(self::GLOBAL_PLUGIN, false));
        $this->assertTrue($this->config->hasPluginClass(self::LOCAL_PLUGIN, false));
    }
}
