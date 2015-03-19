<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Package;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Factory\FactoryManager;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Package\PackageFileStorage;
use Puli\Manager\Package\RootPackageFileManagerImpl;
use Puli\Manager\Tests\ManagerTestCase;
use Puli\Manager\Tests\TestException;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageFileManagerImplTest extends ManagerTestCase
{
    const PLUGIN_NAMESPACE = 'Puli\Manager\Tests\Api\Package\Fixtures';

    const PLUGIN_CLASS = 'Puli\Manager\Tests\Api\Package\Fixtures\TestPlugin';

    const OTHER_PLUGIN_CLASS = 'Puli\Manager\Tests\Api\Package\Fixtures\OtherPlugin';

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * @var RootPackageFileManagerImpl
     */
    private $manager;

    protected function setUp()
    {
        $this->packageFileStorage = $this->getMockBuilder('Puli\Manager\Package\PackageFileStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->baseConfig = new Config();

        $this->initEnvironment(__DIR__.'/Fixtures/home', __DIR__.'/Fixtures/root');

        $this->manager = new RootPackageFileManagerImpl($this->environment, $this->packageFileStorage);
    }

    public function testSetConfigKey()
    {
        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                $config = $packageFile->getConfig();

                PHPUnit_Framework_Assert::assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
            }));

        $this->manager->setConfigKey(Config::PULI_DIR, 'my-puli-dir');
    }

    public function testSetConfigKeys()
    {
        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                $config = $packageFile->getConfig();

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
        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir', $this->manager->getConfigKey(Config::PULI_DIR));
    }

    public function testGetConfigKeyReturnsDefault()
    {
        $this->assertSame('my-puli-dir', $this->manager->getConfigKey(Config::PULI_DIR, 'my-puli-dir'));
    }

    public function testGetConfigKeyReturnsRawValue()
    {
        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');

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

        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::FACTORY_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->getConfigKeys());
    }

    public function testGetConfigKeysReordersToDefaultOrder()
    {
        $this->baseConfig->set(Config::FACTORY_CLASS, 'My\Class');

        $this->rootPackageFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');
        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::FACTORY_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->getConfigKeys());
    }

    public function testGetConfigKeysWithFallback()
    {
        $this->baseConfig->set(Config::FACTORY_CLASS, 'My\Class');

        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::FACTORY_CLASS => 'My\Class',
            Config::FACTORY_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->getConfigKeys(true));
    }

    public function testGetConfigKeysWithAllKeys()
    {
        $this->baseConfig->set(Config::FACTORY_CLASS, 'My\Class');

        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');

        $values = $this->manager->getConfigKeys(false, true);

        $this->assertSame(Config::getKeys(), array_keys($values));
        $this->assertSame('my-puli-dir', $values[Config::PULI_DIR]);
        $this->assertSame('{$puli-dir}/MyFactory.php', $values[Config::FACTORY_FILE]);
        $this->assertNull($values[Config::DISCOVERY_STORE_PATH]);
        $this->assertNull($values[Config::FACTORY_AUTO_GENERATE]);
    }

    public function testFindConfigKeys()
    {
        $this->baseConfig->set(Config::FACTORY_AUTO_GENERATE, true);

        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_CLASS, 'MyFactory');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');

        $this->assertSame(array(
            Config::FACTORY_CLASS => 'MyFactory',
            Config::FACTORY_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->findConfigKeys('factory.*'));
    }

    public function testFindConfigKeysReordersToDefaultOrder()
    {
        $this->baseConfig->set(Config::FACTORY_AUTO_GENERATE, true);

        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_CLASS, 'MyFactory');

        $this->assertSame(array(
            Config::FACTORY_CLASS => 'MyFactory',
            Config::FACTORY_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->findConfigKeys('factory.*'));
    }

    public function testFindConfigKeysWithFallback()
    {
        $this->baseConfig->set(Config::FACTORY_AUTO_GENERATE, true);

        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_CLASS, 'MyFactory');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyFactory.php');

        $this->assertSame(array(
            Config::FACTORY_AUTO_GENERATE => true,
            Config::FACTORY_CLASS => 'MyFactory',
            Config::FACTORY_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->findConfigKeys('factory.*', true));
    }

    public function testFindConfigKeysWithUnsetKeys()
    {
        $this->baseConfig->set(Config::FACTORY_AUTO_GENERATE, true);

        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_CLASS, 'MyFactory');

        $this->assertSame(array(
            Config::FACTORY_AUTO_GENERATE => true,
            Config::FACTORY_CLASS => 'MyFactory',
            Config::FACTORY_FILE => null,
        ), $this->manager->findConfigKeys('factory.*', true, true));
    }

    public function testRemoveConfigKey()
    {
        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_FILE, 'MyFactory.php');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                $config = $packageFile->getConfig();

                PHPUnit_Framework_Assert::assertNull($config->get(Config::PULI_DIR, null, false));
                PHPUnit_Framework_Assert::assertSame('MyFactory.php', $config->get(Config::FACTORY_FILE, null, false));
            }));

        $this->manager->removeConfigKey(Config::PULI_DIR);
    }

    public function testRemoveConfigKeys()
    {
        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_FILE, 'MyFactory.php');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                $config = $packageFile->getConfig();

                PHPUnit_Framework_Assert::assertNull($config->get(Config::PULI_DIR, null, false));
                PHPUnit_Framework_Assert::assertNull($config->get(Config::FACTORY_FILE, null, false));
            }));

        $this->manager->removeConfigKeys(array(Config::PULI_DIR, Config::FACTORY_FILE));
    }

    public function testAddPluginClass()
    {
        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                PHPUnit_Framework_Assert::assertSame(array(RootPackageFileManagerImplTest::PLUGIN_CLASS), $packageFile->getPluginClasses());
            }));

        $this->manager->addPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    public function testAddPluginClassDoesNothingIfAlreadyInstalled()
    {
        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->rootPackageFile->addPluginClass(self::PLUGIN_CLASS);

        $this->manager->addPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Foobar
     */
    public function testAddPluginClassFailsIfClassNotFound()
    {
        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addPluginClass(__NAMESPACE__.'/Foobar');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage is an interface
     */
    public function testAddPluginClassFailsIfInterface()
    {
        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addPluginClass(self::PLUGIN_NAMESPACE.'\TestPluginInterface');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage is a trait
     */
    public function testAddPluginClassFailsIfTrait()
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            $this->markTestSkipped('Traits are only supported on PHP 5.4 and higher');

            return;
        }

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addPluginClass(self::PLUGIN_NAMESPACE.'\TestPluginTrait');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage must implement PuliPlugin
     */
    public function testAddPluginClassFailsIfNoPuliPlugin()
    {
        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addPluginClass('stdClass');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage must not have required parameters
     */
    public function testAddPluginClassFailsIfRequiredConstructorArgs()
    {
        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addPluginClass(self::PLUGIN_NAMESPACE.'\TestPluginWithoutNoArgConstructor');
    }

    public function testRemovePluginClass()
    {
        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                PHPUnit_Framework_Assert::assertSame(array(RootPackageFileManagerImplTest::OTHER_PLUGIN_CLASS), $packageFile->getPluginClasses());
            }));

        $this->rootPackageFile->addPluginClass(self::PLUGIN_CLASS);
        $this->rootPackageFile->addPluginClass(self::OTHER_PLUGIN_CLASS);

        $this->manager->removePluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::OTHER_PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    public function testRemovePluginClassDoesNothingIfNotFound()
    {
        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->rootPackageFile->addPluginClass(self::PLUGIN_CLASS);

        $this->manager->removePluginClass(self::OTHER_PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    public function testClearPluginClasses()
    {
        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                PHPUnit_Framework_Assert::assertSame(array(), $packageFile->getPluginClasses());
            }));

        $this->rootPackageFile->addPluginClass(self::PLUGIN_CLASS);
        $this->rootPackageFile->addPluginClass(self::OTHER_PLUGIN_CLASS);

        $this->manager->clearPluginClasses();

        $this->assertSame(array(), $this->manager->getPluginClasses());
    }

    public function testClearPluginClassesDoesNothingIfNoneExist()
    {
        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->clearPluginClasses();

        $this->assertSame(array(), $this->manager->getPluginClasses());
    }

    public function testHasPluginClass()
    {
        $this->rootPackageFile->addPluginClass(self::PLUGIN_CLASS);

        $this->assertTrue($this->manager->hasPluginClass(self::PLUGIN_CLASS));
        $this->assertFalse($this->manager->hasPluginClass(self::OTHER_PLUGIN_CLASS));

        $this->assertFalse($this->manager->hasPluginClass('foobar'));
    }

    public function testHasPluginClasses()
    {
        $this->assertFalse($this->manager->hasPluginClasses());

        $this->rootPackageFile->addPluginClass(self::PLUGIN_CLASS);

        $this->assertTrue($this->manager->hasPluginClasses());
    }

    public function testGetPackageName()
    {
        // Default name set in initEnvironment()
        $this->assertSame('vendor/root', $this->manager->getPackageName());

        $this->rootPackageFile->setPackageName('vendor/package');

        $this->assertSame('vendor/package', $this->manager->getPackageName());
    }

    public function testSetPackageName()
    {
        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                PHPUnit_Framework_Assert::assertSame('vendor/package', $packageFile->getPackageName());
            }));


        $this->manager->setPackageName('vendor/package');

        $this->assertSame('vendor/package', $this->manager->getPackageName());
    }

    public function testSetPackageNameDoesNotSaveIfUnchanged()
    {
        $this->rootPackageFile->setPackageName('vendor/package');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->setPackageName('vendor/package');

        $this->assertSame('vendor/package', $this->manager->getPackageName());
    }

    public function testSetExtraKey()
    {
        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                PHPUnit_Framework_Assert::assertSame('value', $packageFile->getExtraKey('key'));
            }));

        $this->manager->setExtraKey('key', 'value');
    }

    public function testSetExtraKeyIgnoresDuplicates()
    {
        $this->rootPackageFile->setExtraKey('key', 'value');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->setExtraKey('key', 'value');
    }

    public function testSetExtraKeyRestoresPreviousValueIfSavingFails()
    {
        $this->rootPackageFile->setExtraKey('key', 'previous');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->setExtraKey('key', 'value');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('previous', $this->rootPackageFile->getExtraKey('key'));
    }

    public function testSetExtraKeyRemovesPreviouslyUnsetKeysIfSavingFails()
    {
        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->setExtraKey('key', 'value');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertFalse($this->rootPackageFile->hasExtraKey('key'));
    }

    public function testSetExtraKeys()
    {
        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                PHPUnit_Framework_Assert::assertSame(array(
                    'key1' => 'value1',
                    'key2' => 'value2',
                ), $packageFile->getExtraKeys());
            }));

        $this->manager->setExtraKeys(array(
            'key1' => 'value1',
            'key2' => 'value2',
        ));
    }

    public function testSetExtraKeysIgnoresIfNoneChanged()
    {
        $this->rootPackageFile->setExtraKey('key1', 'value1');
        $this->rootPackageFile->setExtraKey('key2', 'value2');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->setExtraKeys(array(
            'key1' => 'value1',
            'key2' => 'value2',
        ));
    }

    public function testSetExtraKeysRestoresStateIfSavingFails()
    {
        $this->rootPackageFile->setExtraKey('key1', 'previous');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->setExtraKeys(array(
                'key1' => 'value1',
                'key2' => 'value2',
            ));
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('previous', $this->rootPackageFile->getExtraKey('key1'));
        $this->assertFalse($this->rootPackageFile->hasExtraKey('key2'));
    }

    public function testRemoveExtraKey()
    {
        $this->rootPackageFile->setExtraKey('key1', 'value1');
        $this->rootPackageFile->setExtraKey('key2', 'value2');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                PHPUnit_Framework_Assert::assertSame(array(
                    'key2' => 'value2',
                ), $packageFile->getExtraKeys());
            }));

        $this->manager->removeExtraKey('key1');
    }

    public function testRemoveExtraKeyIgnoresNonExisting()
    {
        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeExtraKey('foobar');
    }

    public function testRemoveExtraKeyRestoresValueIfSavingFails()
    {
        $this->rootPackageFile->setExtraKey('key1', 'previous');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->removeExtraKey('key1');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('previous', $this->rootPackageFile->getExtraKey('key1'));
    }

    public function testRemoveExtraKeys()
    {
        $this->rootPackageFile->setExtraKey('key1', 'value1');
        $this->rootPackageFile->setExtraKey('key2', 'value2');
        $this->rootPackageFile->setExtraKey('key3', 'value3');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                PHPUnit_Framework_Assert::assertSame(array(
                    'key2' => 'value2',
                ), $packageFile->getExtraKeys());
            }));

        $this->manager->removeExtraKeys(array('key1', 'key3'));
    }

    public function testRemoveExtraKeysIgnoresIfNoneRemoved()
    {
        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeExtraKeys(array('key1', 'key3'));
    }

    public function testRemoveExtraKeysRestoresPreviousValuesIfSavingFails()
    {
        $this->rootPackageFile->setExtraKey('key1', 'previous');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->removeExtraKeys(array('key1', 'key2'));
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('previous', $this->rootPackageFile->getExtraKey('key1'));
        $this->assertFalse($this->rootPackageFile->hasExtraKey('key2'));
    }

    public function testClearExtraKeys()
    {
        $this->rootPackageFile->setExtraKey('key1', 'value1');
        $this->rootPackageFile->setExtraKey('key2', 'value2');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                PHPUnit_Framework_Assert::assertSame(array(), $packageFile->getExtraKeys());
            }));

        $this->manager->clearExtraKeys();
    }

    public function testClearExtraKeysDoesNothingIfNoneSet()
    {
        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->clearExtraKeys();
    }

    public function testClearExtraKeyRestoresValuesIfSavingFails()
    {
        $this->rootPackageFile->setExtraKey('key1', 'value1');
        $this->rootPackageFile->setExtraKey('key2', 'value2');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->clearExtraKeys();
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('value1', $this->rootPackageFile->getExtraKey('key1'));
        $this->assertSame('value2', $this->rootPackageFile->getExtraKey('key2'));
    }

    public function testHasExtraKey()
    {
        $this->assertFalse($this->manager->hasExtraKey('key'));
        $this->rootPackageFile->setExtraKey('key', 'value');
        $this->assertTrue($this->manager->hasExtraKey('key'));
    }

    public function testHasExtraKeys()
    {
        $this->assertFalse($this->manager->hasExtraKeys());
        $this->rootPackageFile->setExtraKey('key', 'value');
        $this->assertTrue($this->manager->hasExtraKeys());
    }

    public function testGetExtraKey()
    {
        $this->assertNull($this->manager->getExtraKey('key'));
        $this->assertSame('default', $this->manager->getExtraKey('key', 'default'));
        $this->rootPackageFile->setExtraKey('key', 'value');
        $this->assertSame('value', $this->manager->getExtraKey('key'));
    }

    public function testGetExtraKeys()
    {
        $this->assertSame(array(), $this->manager->getExtraKeys());
        $this->rootPackageFile->setExtraKey('key1', 'value1');
        $this->rootPackageFile->setExtraKey('key2', 'value2');
        $this->assertSame(array(
            'key1' => 'value1',
            'key2' => 'value2',
        ), $this->manager->getExtraKeys());
    }
}
