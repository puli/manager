<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Config;

use Puli\PackageManager\Config\GlobalConfig;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GlobalConfigTest extends \PHPUnit_Framework_TestCase
{
    const PLUGIN_CLASS = 'Puli\PackageManager\Tests\Config\Fixtures\TestPlugin';
    const OTHER_PLUGIN_CLASS = 'Puli\PackageManager\Tests\Config\Fixtures\OtherPlugin';
    /**
     * @var GlobalConfig
     */
    private $config;

    protected function setUp()
    {
        $this->config = new GlobalConfig();
    }

    public function testGetInstallFile()
    {
        $this->config->setInstallFile('custom/packages.json');

        $this->assertSame('custom/packages.json', $this->config->getInstallFile());
        $this->assertSame('custom/packages.json', $this->config->getInstallFile(false));
    }

    public function testGetInstallFileWhenNoneIsSet()
    {
        $this->assertSame('.puli/packages.json', $this->config->getInstallFile());
        $this->assertNull($this->config->getInstallFile(false));
    }

    public function testGetInstallFileWhenSetToNull()
    {
        $this->config->setInstallFile('custom/packages.json');
        $this->config->setInstallFile(null);

        $this->assertSame('.puli/packages.json', $this->config->getInstallFile());
        $this->assertNull($this->config->getInstallFile(false));
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testInstallFileMustBeString()
    {
        $this->config->setInstallFile(12345);
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testInstallFileMustNotBeEmpty()
    {
        $this->config->setInstallFile('');
    }

    public function testGetGeneratedResourceRepository()
    {
        $this->config->setGeneratedResourceRepository('custom/resource-repository.php');

        $this->assertSame('custom/resource-repository.php', $this->config->getGeneratedResourceRepository());
        $this->assertSame('custom/resource-repository.php', $this->config->getGeneratedResourceRepository(false));
    }

    public function testGetGeneratedResourceRepositoryWhenNoneIsSet()
    {
        $this->assertSame('.puli/resource-repository.php', $this->config->getGeneratedResourceRepository());
        $this->assertNull($this->config->getGeneratedResourceRepository(false));
    }

    public function testGetGeneratedResourceRepositoryWhenSetToNull()
    {
        $this->config->setGeneratedResourceRepository('custom/resource-repository.php');
        $this->config->setGeneratedResourceRepository(null);

        $this->assertSame('.puli/resource-repository.php', $this->config->getGeneratedResourceRepository());
        $this->assertNull($this->config->getGeneratedResourceRepository(false));
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testGeneratedResourceRepositoryMustBeString()
    {
        $this->config->setGeneratedResourceRepository(12345);
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testGeneratedResourceRepositoryMustNotBeEmpty()
    {
        $this->config->setGeneratedResourceRepository('');
    }

    public function testGetResourceRepositoryCache()
    {
        $this->config->setResourceRepositoryCache('custom/cache');

        $this->assertSame('custom/cache', $this->config->getResourceRepositoryCache());
        $this->assertSame('custom/cache', $this->config->getResourceRepositoryCache(false));
    }

    public function testGetResourceRepositoryCacheWhenNoneIsSet()
    {
        $this->assertSame('.puli/cache', $this->config->getResourceRepositoryCache());
        $this->assertNull($this->config->getResourceRepositoryCache(false));
    }

    public function testGetResourceRepositoryCacheWhenSetToNull()
    {
        $this->config->setResourceRepositoryCache('custom/cache');
        $this->config->setResourceRepositoryCache(null);

        $this->assertSame('.puli/cache', $this->config->getResourceRepositoryCache());
        $this->assertNull($this->config->getResourceRepositoryCache(false));
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testResourceRepositoryCacheMustBeString()
    {
        $this->config->setResourceRepositoryCache(12345);
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testResourceRepositoryCacheMustNotBeEmpty()
    {
        $this->config->setResourceRepositoryCache('');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testPluginClassMustBeExistingClass()
    {
        $this->config->addPluginClass('\Puli\Foobar');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testPluginClassMustImplementPluginInterface()
    {
        $this->config->addPluginClass('\stdClass');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testPluginClassMustHaveNoArgConstructor()
    {
        $this->config->addPluginClass('Puli\PackageManager\Tests\Config\Fixtures\TestPluginWithoutNoArgConstructor');
    }

    public function testPluginClassMayHaveNoConstructor()
    {
        $this->config->addPluginClass('Puli\PackageManager\Tests\Config\Fixtures\TestPluginWithoutConstructor');

        $this->assertSame(array('Puli\PackageManager\Tests\Config\Fixtures\TestPluginWithoutConstructor'), $this->config->getPluginClasses());
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testPluginClassMustNotBeInterface()
    {
        $this->config->addPluginClass('Puli\PackageManager\Tests\Config\Fixtures\TestPluginInterface');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testPluginClassMustNotBeTrait()
    {
        $this->config->addPluginClass('Puli\PackageManager\Tests\Config\Fixtures\TestPluginTrait');
    }

    public function testDuplicatePluginClassesIgnored()
    {
        $this->config->addPluginClass(self::PLUGIN_CLASS);
        $this->config->addPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->config->getPluginClasses());
    }

    public function testAddPluginClassIgnoresLeadingSlash()
    {
        $this->config->addPluginClass('\\'.self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->config->getPluginClasses());
    }

    public function testSetPluginClasses()
    {
        $this->config->setPluginClasses(array(
            self::PLUGIN_CLASS,
            self::OTHER_PLUGIN_CLASS,
        ));

        $this->assertSame(array(self::PLUGIN_CLASS, self::OTHER_PLUGIN_CLASS), $this->config->getPluginClasses());
    }

    public function testSetPluginClassesToEmptyArray()
    {
        $this->config->addPluginClass(self::PLUGIN_CLASS);
        $this->config->setPluginClasses(array());

        $this->assertSame(array(), $this->config->getPluginClasses());
    }

    public function testRemovePluginClass()
    {
        $this->config->setPluginClasses(array(
            self::PLUGIN_CLASS,
            self::OTHER_PLUGIN_CLASS,
        ));
        $this->config->removePluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::OTHER_PLUGIN_CLASS), $this->config->getPluginClasses());
    }

    public function testRemovePluginClassIgnoresLeadingSlash()
    {
        $this->config->setPluginClasses(array(
            self::PLUGIN_CLASS,
            self::OTHER_PLUGIN_CLASS,
        ));
        $this->config->removePluginClass('\\'.self::PLUGIN_CLASS);

        $this->assertSame(array(self::OTHER_PLUGIN_CLASS), $this->config->getPluginClasses());
    }

    public function testRemovePluginClassDoesNothingIfNotFound()
    {
        $this->config->addPluginClass(self::PLUGIN_CLASS);
        $this->config->removePluginClass(self::OTHER_PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->config->getPluginClasses());
    }

    public function testHasPluginClass()
    {
        $this->assertFalse($this->config->hasPluginClass(self::PLUGIN_CLASS));

        $this->config->addPluginClass(self::PLUGIN_CLASS);

        $this->assertTrue($this->config->hasPluginClass(self::PLUGIN_CLASS));
    }

    public function testHasPluginClassIgnoresLeadingSlash()
    {
        $this->assertFalse($this->config->hasPluginClass('\\'.self::PLUGIN_CLASS));

        $this->config->addPluginClass(self::PLUGIN_CLASS);

        $this->assertTrue($this->config->hasPluginClass('\\'.self::PLUGIN_CLASS));
    }
    public function provideValidPaths()
    {
        return array(
            array(null),
            array('/foo'),
        );
    }

    /**
     * @dataProvider provideValidPaths
     */
    public function testGetPath($path)
    {
        $config = new GlobalConfig($path);

        $this->assertSame($path, $config->getPath());
    }

    public function provideInvalidPaths()
    {
        return array(
            array(12345),
            array(''),
        );
    }

    /**
     * @dataProvider provideInvalidPaths
     * @expectedException \InvalidArgumentException
     */
    public function testPathMustBeValid($invalidPath)
    {
        new GlobalConfig($invalidPath);
    }
}
