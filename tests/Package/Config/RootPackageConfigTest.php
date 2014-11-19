<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Package\Config;

use Puli\PackageManager\Package\Config\RootPackageConfig;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageConfigTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RootPackageConfig
     */
    private $config;

    protected function setUp()
    {
        $this->config = new RootPackageConfig();
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
}
