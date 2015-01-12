<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Config\ConfigFile;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFile;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFileManager;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFileStorage;
use Puli\RepositoryManager\Tests\Package\Fixtures\TestGlobalEnvironment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigFileManagerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $homeDir;

    /**
     * @var Config
     */
    private $baseConfig;

    /**
     * @var ConfigFile
     */
    private $configFile;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var TestGlobalEnvironment
     */
    private $environment;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ConfigFileStorage
     */
    private $configFileStorage;

    /**
     * @var ConfigFileManager
     */
    private $manager;

    protected function setUp()
    {
        $this->homeDir = __DIR__.'/Fixtures/home';
        $this->baseConfig = new Config();
        $this->configFile = new ConfigFile(null, $this->baseConfig);
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $this->environment = new TestGlobalEnvironment(
            $this->homeDir,
            $this->configFile,
            $this->dispatcher
        );

        $this->configFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Config\ConfigFile\ConfigFileStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->manager = new ConfigFileManager($this->environment, $this->configFileStorage);
    }

    public function testSetConfigKey()
    {
        $this->configFileStorage->expects($this->once())
            ->method('saveConfigFile')
            ->with($this->configFile)
            ->will($this->returnCallback(function (ConfigFile $configFile) {
                $config = $configFile->getConfig();

                PHPUnit_Framework_Assert::assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
            }));

        $this->manager->setConfigKey(Config::PULI_DIR, 'my-puli-dir');
    }

    public function testSetConfigKeys()
    {
        $this->configFileStorage->expects($this->once())
            ->method('saveConfigFile')
            ->with($this->configFile)
            ->will($this->returnCallback(function (ConfigFile $configFile) {
                $config = $configFile->getConfig();

                PHPUnit_Framework_Assert::assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
                PHPUnit_Framework_Assert::assertSame('my-puli-dir/MyFactory.php', $config->get(Config::FACTORY_FILE));
            }));

        $this->manager->setConfigKeys(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::FACTORY_FILE => '{$puli-dir}/MyFactory.php',
        ));
    }

    public function testGetConfigKey()
    {
        $this->assertNull($this->manager->getConfigKey(Config::PULI_DIR));

        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir', $this->manager->getConfigKey(Config::PULI_DIR));
    }

    public function testGetConfigKeyWithDefault()
    {
        $this->assertSame('my-puli-dir', $this->manager->getConfigKey(Config::PULI_DIR, 'my-puli-dir'));
    }

    public function testGetConfigKeyReturnsRawValue()
    {
        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->configFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');

        $this->assertSame('{$puli-dir}/MyFactory.php', $this->manager->getConfigKey(Config::FACTORY_FILE));
    }

    public function testGetConfigKeyReturnsNullIfNotSet()
    {
        $this->baseConfig->set(Config::PULI_DIR, 'fallback');

        $this->assertNull($this->manager->getConfigKey(Config::PULI_DIR));
    }

    public function testGetConfigKeyReturnsFallbackIfRequested()
    {
        $this->baseConfig->set(Config::PULI_DIR, 'fallback');

        $this->assertSame('fallback', $this->manager->getConfigKey(Config::PULI_DIR, null, true));
    }

    public function testGetConfigKeys()
    {
        $this->baseConfig->set(Config::FACTORY_CLASS, 'My\Class');

        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->configFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::FACTORY_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->getConfigKeys());
    }

    public function testGetConfigKeysReordersToDefaultOrder()
    {
        $this->baseConfig->set(Config::FACTORY_CLASS, 'My\Class');

        $this->configFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');
        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::FACTORY_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->getConfigKeys());
    }

    public function testGetConfigKeysWithFallback()
    {
        $this->baseConfig->set(Config::FACTORY_CLASS, 'My\Class');

        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->configFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::FACTORY_CLASS => 'My\Class',
            Config::FACTORY_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->getConfigKeys(true));
    }

    public function testGetConfigKeysWithAllKeys()
    {
        $this->baseConfig->set(Config::FACTORY_CLASS, 'My\Class');

        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->configFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');

        $values = $this->manager->getConfigKeys(false, true);

        $this->assertSame(Config::getKeys(), array_keys($values));
        $this->assertSame('my-puli-dir', $values[Config::PULI_DIR]);
        $this->assertSame('{$puli-dir}/MyFactory.php', $values[Config::FACTORY_FILE]);
        $this->assertNull($values[Config::REPOSITORY_PATH]);
        $this->assertNull($values[Config::FACTORY_AUTO_GENERATE]);
    }

    public function testFindConfigKeys()
    {
        $this->baseConfig->set(Config::FACTORY_AUTO_GENERATE, true);

        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->configFile->getConfig()->set(Config::FACTORY_CLASS, 'MyFactory');
        $this->configFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');

        $this->assertSame(array(
            Config::FACTORY_CLASS => 'MyFactory',
            Config::FACTORY_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->findConfigKeys('factory.*'));
    }

    public function testFindConfigKeysReordersToDefaultOrder()
    {
        $this->baseConfig->set(Config::FACTORY_AUTO_GENERATE, true);

        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->configFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');
        $this->configFile->getConfig()->set(Config::FACTORY_CLASS, 'MyFactory');

        $this->assertSame(array(
            Config::FACTORY_CLASS => 'MyFactory',
            Config::FACTORY_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->findConfigKeys('factory.*'));
    }

    public function testFindConfigKeysWithFallback()
    {
        $this->baseConfig->set(Config::FACTORY_AUTO_GENERATE, true);

        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->configFile->getConfig()->set(Config::FACTORY_CLASS, 'MyFactory');
        $this->configFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');

        $this->assertSame(array(
            Config::FACTORY_AUTO_GENERATE => true,
            Config::FACTORY_CLASS => 'MyFactory',
            Config::FACTORY_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->findConfigKeys('factory.*', true));
    }

    public function testFindConfigKeysWithUnsetKeys()
    {
        $this->baseConfig->set(Config::FACTORY_AUTO_GENERATE, true);

        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->configFile->getConfig()->set(Config::FACTORY_CLASS, 'MyFactory');

        $this->assertSame(array(
            Config::FACTORY_AUTO_GENERATE => true,
            Config::FACTORY_CLASS => 'MyFactory',
            Config::FACTORY_FILE => null,
        ), $this->manager->findConfigKeys('factory.*', true, true));
    }

    public function testRemoveConfigKey()
    {
        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->configFile->getConfig()->set(Config::FACTORY_FILE, 'MyServiceRegistry.php');

        $this->configFileStorage->expects($this->once())
            ->method('saveConfigFile')
            ->with($this->configFile)
            ->will($this->returnCallback(function (ConfigFile $configFile) {
                $config = $configFile->getConfig();

                PHPUnit_Framework_Assert::assertNull($config->get(Config::PULI_DIR, null, false));
                PHPUnit_Framework_Assert::assertSame('MyServiceRegistry.php', $config->get(Config::FACTORY_FILE, null, false));
            }));

        $this->manager->removeConfigKey(Config::PULI_DIR);
    }

    public function testRemoveConfigKeys()
    {
        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->configFile->getConfig()->set(Config::FACTORY_FILE, 'MyServiceRegistry.php');

        $this->configFileStorage->expects($this->once())
            ->method('saveConfigFile')
            ->with($this->configFile)
            ->will($this->returnCallback(function (ConfigFile $configFile) {
                $config = $configFile->getConfig();

                PHPUnit_Framework_Assert::assertNull($config->get(Config::PULI_DIR, null, false));
                PHPUnit_Framework_Assert::assertNull($config->get(Config::FACTORY_FILE, null, false));
            }));

        $this->manager->removeConfigKeys(array(Config::PULI_DIR, Config::FACTORY_FILE));
    }
}
