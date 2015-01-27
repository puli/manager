<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Config;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Api\Config\Config;
use Puli\RepositoryManager\Api\Config\ConfigFile;
use Puli\RepositoryManager\Config\ConfigFileManagerImpl;
use Puli\RepositoryManager\Config\ConfigFileStorage;
use Puli\RepositoryManager\Tests\Package\Fixtures\TestGlobalEnvironment;
use Puli\RepositoryManager\Tests\TestException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigFileManagerImplTest extends PHPUnit_Framework_TestCase
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
     * @var ConfigFileManagerImpl
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

        $this->configFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Config\ConfigFileStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->manager = new ConfigFileManagerImpl($this->environment, $this->configFileStorage);
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

    public function testSetConfigKeyRevertsIfSavingNotPossible()
    {
        $this->configFileStorage->expects($this->once())
            ->method('saveConfigFile')
            ->willThrowException(new TestException());

        try {
            $this->manager->setConfigKey(Config::PULI_DIR, 'my-puli-dir');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertFalse($this->configFile->getConfig()->contains(Config::PULI_DIR));
    }

    public function testSetConfigKeyResetsToPreviousValueIfSavingNotPossible()
    {
        $this->configFileStorage->expects($this->once())
            ->method('saveConfigFile')
            ->willThrowException(new TestException());

        $this->configFile->getConfig()->set(Config::PULI_DIR, 'previous-value');

        try {
            $this->manager->setConfigKey(Config::PULI_DIR, 'my-puli-dir');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('previous-value', $this->configFile->getConfig()->get(Config::PULI_DIR));
    }

    public function testSetConfigKeyIgnoresUnchangedValues()
    {
        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');

        $this->configFileStorage->expects($this->never())
            ->method('saveConfigFile');

        $this->manager->setConfigKey(Config::PULI_DIR, 'my-puli-dir');
    }

    public function testSetConfigKeyAcceptsNewFalseValue()
    {
        $this->configFileStorage->expects($this->once())
            ->method('saveConfigFile')
            ->with($this->configFile)
            ->will($this->returnCallback(function (ConfigFile $configFile) {
                $config = $configFile->getConfig();

                PHPUnit_Framework_Assert::assertFalse($config->get(Config::FACTORY_AUTO_GENERATE));
            }));

        $this->manager->setConfigKey(Config::FACTORY_AUTO_GENERATE, false);
    }

    public function testSetConfigKeyAcceptsNewNullValue()
    {
        $this->configFileStorage->expects($this->once())
            ->method('saveConfigFile')
            ->with($this->configFile)
            ->will($this->returnCallback(function (ConfigFile $configFile) {
                $config = $configFile->getConfig();

                PHPUnit_Framework_Assert::assertNull($config->get(Config::DISCOVERY_STORE_TYPE));
            }));

        $this->manager->setConfigKey(Config::DISCOVERY_STORE_TYPE, null);
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

    public function testSetConfigKeysRevertsIfSavingNotPossible()
    {
        $this->configFileStorage->expects($this->once())
            ->method('saveConfigFile')
            ->willThrowException(new TestException());

        $this->configFile->getConfig()->set(Config::PULI_DIR, 'previous-value');

        try {
            $this->manager->setConfigKeys(array(
                Config::PULI_DIR => 'my-puli-dir',
                Config::FACTORY_FILE => '{$puli-dir}/MyFactory.php',
            ));
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('previous-value', $this->configFile->getConfig()->get(Config::PULI_DIR));
        $this->assertFalse($this->configFile->getConfig()->contains(Config::FACTORY_FILE));
    }

    public function testHasConfigKey()
    {
        $this->assertFalse($this->manager->hasConfigKey(Config::PULI_DIR));

        $this->baseConfig->set(Config::PULI_DIR, 'fallback');

        $this->assertFalse($this->manager->hasConfigKey(Config::PULI_DIR));

        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertTrue($this->manager->hasConfigKey(Config::PULI_DIR));
    }

    public function testHasConfigKeyWithFallback()
    {
        $this->assertFalse($this->manager->hasConfigKey(Config::PULI_DIR, true));

        $this->baseConfig->set(Config::PULI_DIR, 'fallback');

        $this->assertTrue($this->manager->hasConfigKey(Config::PULI_DIR, true));

        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertTrue($this->manager->hasConfigKey(Config::PULI_DIR, true));
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

    public function testRemoveConfigKeyRevertsIfSavingNotPossible()
    {
        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->configFile->getConfig()->set(Config::FACTORY_FILE, 'MyServiceRegistry.php');

        $this->configFileStorage->expects($this->once())
            ->method('saveConfigFile')
            ->willThrowException(new TestException());

        try {
            $this->manager->removeConfigKey(Config::PULI_DIR);
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($this->configFile->getConfig()->contains(Config::PULI_DIR));
        $this->assertTrue($this->configFile->getConfig()->contains(Config::FACTORY_FILE));
        $this->assertSame('my-puli-dir', $this->configFile->getConfig()->get(Config::PULI_DIR));
    }

    public function testRemoveConfigKeyIgnoresUnsetValues()
    {
        $this->configFileStorage->expects($this->never())
            ->method('saveConfigFile');

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

    public function testRemoveConfigKeysRevertsIfSavingNotPossible()
    {
        $this->configFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');

        $this->configFileStorage->expects($this->once())
            ->method('saveConfigFile')
            ->willThrowException(new TestException());

        try {
            $this->manager->removeConfigKeys(array(Config::PULI_DIR, Config::FACTORY_FILE));
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($this->configFile->getConfig()->contains(Config::PULI_DIR));
        $this->assertFalse($this->configFile->getConfig()->contains(Config::FACTORY_FILE));
        $this->assertSame('my-puli-dir', $this->configFile->getConfig()->get(Config::PULI_DIR));
    }
}
