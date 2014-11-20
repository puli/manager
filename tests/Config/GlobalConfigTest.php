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
    /**
     * @var GlobalConfig
     */
    private $config;

    protected function setUp()
    {
        $this->config = new GlobalConfig();
    }

    public function testGetPackageRepositoryConfig()
    {
        $this->config->setPackageRepositoryConfig('custom/packages.json');

        $this->assertSame('custom/packages.json', $this->config->getPackageRepositoryConfig());
        $this->assertSame('custom/packages.json', $this->config->getPackageRepositoryConfig(false));
    }

    public function testGetPackageRepositoryConfigWhenNoneIsSet()
    {
        $this->assertSame('.puli/packages.json', $this->config->getPackageRepositoryConfig());
        $this->assertNull($this->config->getPackageRepositoryConfig(false));
    }

    public function testGetPackageRepositoryConfigWhenSetToNull()
    {
        $this->config->setPackageRepositoryConfig('custom/packages.json');
        $this->config->setPackageRepositoryConfig(null);

        $this->assertSame('.puli/packages.json', $this->config->getPackageRepositoryConfig());
        $this->assertNull($this->config->getPackageRepositoryConfig(false));
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testPackageRepositoryConfigMustBeString()
    {
        $this->config->setPackageRepositoryConfig(12345);
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testPackageRepositoryConfigMustNotBeEmpty()
    {
        $this->config->setPackageRepositoryConfig('');
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
        $this->config->addPluginClass(__NAMESPACE__.'\Fixtures\TestPluginWithoutNoArgConstructor');
    }

    public function testPluginClassMayHaveNoConstructor()
    {
        $this->config->addPluginClass(__NAMESPACE__.'\Fixtures\TestPluginWithoutConstructor');

        $this->assertSame(array(__NAMESPACE__.'\Fixtures\TestPluginWithoutConstructor'), $this->config->getPluginClasses());
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testPluginClassMustNotBeInterface()
    {
        $this->config->addPluginClass(__NAMESPACE__.'\Fixtures\TestPluginInterface');
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testPluginClassMustNotBeTrait()
    {
        $this->config->addPluginClass(__NAMESPACE__.'\Fixtures\TestPluginTrait');
    }

    public function testDuplicatePluginClassesIgnored()
    {
        $this->config->addPluginClass(__NAMESPACE__.'\Fixtures\TestPlugin');
        $this->config->addPluginClass(__NAMESPACE__.'\Fixtures\TestPlugin');

        $this->assertSame(array(__NAMESPACE__.'\Fixtures\TestPlugin'), $this->config->getPluginClasses());
    }

    public function testAddPluginClassIgnoresLeadingSlash()
    {
        $this->config->addPluginClass('\\'.__NAMESPACE__.'\Fixtures\TestPlugin');

        $this->assertSame(array(__NAMESPACE__.'\Fixtures\TestPlugin'), $this->config->getPluginClasses());
    }

    public function testSetPluginClasses()
    {
        $this->config->setPluginClasses(array(
            __NAMESPACE__.'\Fixtures\TestPlugin',
            __NAMESPACE__.'\Fixtures\OtherPlugin',
        ));

        $this->assertSame(array(__NAMESPACE__.'\Fixtures\TestPlugin', __NAMESPACE__.'\Fixtures\OtherPlugin'), $this->config->getPluginClasses());
    }

    public function testSetPluginClassesToEmptyArray()
    {
        $this->config->addPluginClass(__NAMESPACE__.'\Fixtures\TestPlugin');
        $this->config->setPluginClasses(array());

        $this->assertSame(array(), $this->config->getPluginClasses());
    }

    public function testRemovePluginClass()
    {
        $this->config->setPluginClasses(array(
            __NAMESPACE__.'\Fixtures\TestPlugin',
            __NAMESPACE__.'\Fixtures\OtherPlugin',
        ));
        $this->config->removePluginClass(__NAMESPACE__.'\Fixtures\TestPlugin');

        $this->assertSame(array(__NAMESPACE__.'\Fixtures\OtherPlugin'), $this->config->getPluginClasses());
    }

    public function testRemovePluginClassIgnoresLeadingSlash()
    {
        $this->config->setPluginClasses(array(
            __NAMESPACE__.'\Fixtures\TestPlugin',
            __NAMESPACE__.'\Fixtures\OtherPlugin',
        ));
        $this->config->removePluginClass('\\'.__NAMESPACE__.'\Fixtures\TestPlugin');

        $this->assertSame(array(__NAMESPACE__.'\Fixtures\OtherPlugin'), $this->config->getPluginClasses());
    }

    public function testRemovePluginClassDoesNothingIfNotFound()
    {
        $this->config->addPluginClass(__NAMESPACE__.'\Fixtures\TestPlugin');
        $this->config->removePluginClass(__NAMESPACE__.'\Fixtures\OtherPlugin');

        $this->assertSame(array(__NAMESPACE__.'\Fixtures\TestPlugin'), $this->config->getPluginClasses());
    }
}
