<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Module;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Json\JsonStorage;
use Puli\Manager\Module\RootModuleFileManagerImpl;
use Puli\Manager\Tests\ManagerTestCase;
use Puli\Manager\Tests\TestException;
use Webmozart\Expression\Expr;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootModuleFileManagerImplTest extends ManagerTestCase
{
    const PLUGIN_NAMESPACE = 'Puli\Manager\Tests\Api\Module\Fixtures';

    const PLUGIN_CLASS = 'Puli\Manager\Tests\Api\Module\Fixtures\TestPlugin';

    const OTHER_PLUGIN_CLASS = 'Puli\Manager\Tests\Api\Module\Fixtures\OtherPlugin';

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|JsonStorage
     */
    private $jsonStorage;

    /**
     * @var RootModuleFileManagerImpl
     */
    private $manager;

    protected function setUp()
    {
        $this->jsonStorage = $this->getMockBuilder('Puli\Manager\Json\JsonStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->baseConfig = new Config();

        $this->initContext(__DIR__.'/Fixtures/home', __DIR__.'/Fixtures/root');

        $this->manager = new RootModuleFileManagerImpl($this->context, $this->jsonStorage);
    }

    public function testSetConfigKey()
    {
        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $moduleFile) {
                $config = $moduleFile->getConfig();

                PHPUnit_Framework_Assert::assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
            }));

        $this->manager->setConfigKey(Config::PULI_DIR, 'my-puli-dir');
    }

    public function testSetConfigKeys()
    {
        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $moduleFile) {
                $config = $moduleFile->getConfig();

                PHPUnit_Framework_Assert::assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
                PHPUnit_Framework_Assert::assertSame('my-puli-dir/MyFactory.php', $config->get(Config::FACTORY_IN_FILE));
            }));

        $this->manager->setConfigKeys(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::FACTORY_IN_FILE => '{$puli-dir}/MyFactory.php',
        ));
    }

    public function testGetConfigKey()
    {
        $this->rootModuleFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir', $this->manager->getConfigKey(Config::PULI_DIR));
    }

    public function testGetConfigKeyReturnsDefault()
    {
        $this->assertSame('my-puli-dir', $this->manager->getConfigKey(Config::PULI_DIR, 'my-puli-dir'));
    }

    public function testGetConfigKeyReturnsRawValue()
    {
        $this->rootModuleFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootModuleFile->getConfig()->set(Config::FACTORY_IN_FILE, '{$puli-dir}/MyFactory.php');

        $this->assertSame('{$puli-dir}/MyFactory.php', $this->manager->getConfigKey(Config::FACTORY_IN_FILE));
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
        $this->baseConfig->set(Config::FACTORY_IN_CLASS, 'My\Class');

        $this->rootModuleFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootModuleFile->getConfig()->set(Config::FACTORY_IN_FILE, '{$puli-dir}/MyFactory.php');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::FACTORY_IN_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->getConfigKeys());
    }

    public function testGetConfigKeysReordersToDefaultOrder()
    {
        $this->baseConfig->set(Config::FACTORY_IN_CLASS, 'My\Class');

        $this->rootModuleFile->getConfig()->set(Config::FACTORY_IN_FILE, '{$puli-dir}/MyFactory.php');
        $this->rootModuleFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::FACTORY_IN_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->getConfigKeys());
    }

    public function testGetConfigKeysWithFallback()
    {
        $this->baseConfig->set(Config::FACTORY_IN_CLASS, 'My\Class');

        $this->rootModuleFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootModuleFile->getConfig()->set(Config::FACTORY_IN_FILE, '{$puli-dir}/MyFactory.php');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::FACTORY_IN_CLASS => 'My\Class',
            Config::FACTORY_IN_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->getConfigKeys(true));
    }

    public function testGetConfigKeysWithAllKeys()
    {
        $this->baseConfig->set(Config::FACTORY_IN_CLASS, 'My\Class');

        $this->rootModuleFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootModuleFile->getConfig()->set(Config::FACTORY_IN_FILE, '{$puli-dir}/MyFactory.php');

        $values = $this->manager->getConfigKeys(false, true);

        $this->assertSame(Config::getKeys(), array_keys($values));
        $this->assertSame('my-puli-dir', $values[Config::PULI_DIR]);
        $this->assertSame('{$puli-dir}/MyFactory.php', $values[Config::FACTORY_IN_FILE]);
        $this->assertNull($values[Config::DISCOVERY_STORE_PATH]);
        $this->assertNull($values[Config::FACTORY_AUTO_GENERATE]);
    }

    public function testFindConfigKeys()
    {
        $this->baseConfig->set(Config::FACTORY_AUTO_GENERATE, true);

        $this->rootModuleFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootModuleFile->getConfig()->set(Config::FACTORY_IN_CLASS, 'MyFactory');
        $this->rootModuleFile->getConfig()->set(Config::FACTORY_IN_FILE, '{$puli-dir}/MyFactory.php');

        $this->assertSame(array(
            Config::FACTORY_IN_CLASS => 'MyFactory',
            Config::FACTORY_IN_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->findConfigKeys(Expr::startsWith('factory.')));
    }

    public function testFindConfigKeysReordersToDefaultOrder()
    {
        $this->baseConfig->set(Config::FACTORY_AUTO_GENERATE, true);

        $this->rootModuleFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootModuleFile->getConfig()->set(Config::FACTORY_IN_FILE, '{$puli-dir}/MyFactory.php');
        $this->rootModuleFile->getConfig()->set(Config::FACTORY_IN_CLASS, 'MyFactory');

        $this->assertSame(array(
            Config::FACTORY_IN_CLASS => 'MyFactory',
            Config::FACTORY_IN_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->findConfigKeys(Expr::startsWith('factory.')));
    }

    public function testFindConfigKeysWithFallback()
    {
        $this->baseConfig->set(Config::FACTORY_AUTO_GENERATE, true);

        $this->rootModuleFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootModuleFile->getConfig()->set(Config::FACTORY_IN_CLASS, 'MyFactory');
        $this->rootModuleFile->getConfig()->set(Config::FACTORY_IN_FILE, '{$puli-dir}/MyFactory.php');

        $this->assertSame(array(
            Config::FACTORY_AUTO_GENERATE => true,
            Config::FACTORY_IN_CLASS => 'MyFactory',
            Config::FACTORY_IN_FILE => '{$puli-dir}/MyFactory.php',
        ), $this->manager->findConfigKeys(Expr::startsWith('factory.'), true));
    }

    public function testFindConfigKeysWithUnsetKeys()
    {
        $this->baseConfig->set(Config::FACTORY_AUTO_GENERATE, true);

        $this->rootModuleFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootModuleFile->getConfig()->set(Config::FACTORY_IN_CLASS, 'MyFactory');

        $this->assertSame(array(
            Config::FACTORY_AUTO_GENERATE => true,
            Config::FACTORY_IN_CLASS => 'MyFactory',
            Config::FACTORY_IN_FILE => null,
            Config::FACTORY_OUT_CLASS => null,
            Config::FACTORY_OUT_FILE => null,
        ), $this->manager->findConfigKeys(Expr::startsWith('factory.'), true, true));
    }

    public function testRemoveConfigKey()
    {
        $this->rootModuleFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootModuleFile->getConfig()->set(Config::FACTORY_IN_FILE, 'MyFactory.php');

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $moduleFile) {
                $config = $moduleFile->getConfig();

                PHPUnit_Framework_Assert::assertNull($config->get(Config::PULI_DIR, null, false));
                PHPUnit_Framework_Assert::assertSame('MyFactory.php', $config->get(Config::FACTORY_IN_FILE, null, false));
            }));

        $this->manager->removeConfigKey(Config::PULI_DIR);
    }

    public function testRemoveConfigKeys()
    {
        $this->rootModuleFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootModuleFile->getConfig()->set(Config::FACTORY_IN_FILE, 'MyFactory.php');

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $moduleFile) {
                $config = $moduleFile->getConfig();

                PHPUnit_Framework_Assert::assertNull($config->get(Config::PULI_DIR, null, false));
                PHPUnit_Framework_Assert::assertNull($config->get(Config::FACTORY_IN_FILE, null, false));
            }));

        $this->manager->removeConfigKeys(Expr::in(array(Config::PULI_DIR, Config::FACTORY_IN_FILE)));
    }

    public function testAddPluginClass()
    {
        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $moduleFile) {
                PHPUnit_Framework_Assert::assertSame(array(RootModuleFileManagerImplTest::PLUGIN_CLASS), $moduleFile->getPluginClasses());
            }));

        $this->manager->addPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    public function testAddPluginClassDoesNothingIfAlreadyInstalled()
    {
        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->rootModuleFile->addPluginClass(self::PLUGIN_CLASS);

        $this->manager->addPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Foobar
     */
    public function testAddPluginClassFailsIfClassNotFound()
    {
        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->addPluginClass(__NAMESPACE__.'/Foobar');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage is an interface
     */
    public function testAddPluginClassFailsIfInterface()
    {
        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

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

        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->addPluginClass(self::PLUGIN_NAMESPACE.'\TestPluginTrait');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage must implement PuliPlugin
     */
    public function testAddPluginClassFailsIfNoPuliPlugin()
    {
        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->addPluginClass('stdClass');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage must not have required parameters
     */
    public function testAddPluginClassFailsIfRequiredConstructorArgs()
    {
        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->addPluginClass(self::PLUGIN_NAMESPACE.'\TestPluginWithoutNoArgConstructor');
    }

    public function testAddPluginClassRevertsIfSavingFails()
    {
        $this->rootModuleFile->addPluginClass(self::OTHER_PLUGIN_CLASS);

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->addPluginClass(self::PLUGIN_CLASS);
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame(array(self::OTHER_PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    public function testRemovePluginClass()
    {
        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $moduleFile) {
                PHPUnit_Framework_Assert::assertSame(array(RootModuleFileManagerImplTest::OTHER_PLUGIN_CLASS), $moduleFile->getPluginClasses());
            }));

        $this->rootModuleFile->addPluginClass(self::PLUGIN_CLASS);
        $this->rootModuleFile->addPluginClass(self::OTHER_PLUGIN_CLASS);

        $this->manager->removePluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::OTHER_PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    public function testRemovePluginClassDoesNothingIfNotFound()
    {
        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->rootModuleFile->addPluginClass(self::PLUGIN_CLASS);

        $this->manager->removePluginClass(self::OTHER_PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    public function testRemovePluginClassRevertsIfSavingFails()
    {
        $this->rootModuleFile->addPluginClass(self::PLUGIN_CLASS);
        $this->rootModuleFile->addPluginClass(self::OTHER_PLUGIN_CLASS);

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->removePluginClass(self::PLUGIN_CLASS);
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame(array(self::PLUGIN_CLASS, self::OTHER_PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    public function testRemovePluginClasses()
    {
        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $moduleFile) {
                PHPUnit_Framework_Assert::assertSame(array(), $moduleFile->getPluginClasses());
            }));

        $this->rootModuleFile->addPluginClass(self::PLUGIN_CLASS);
        $this->rootModuleFile->addPluginClass(self::OTHER_PLUGIN_CLASS);

        $this->manager->removePluginClasses(Expr::in(array(self::PLUGIN_CLASS, self::OTHER_PLUGIN_CLASS)));

        $this->assertSame(array(), $this->manager->getPluginClasses());
    }

    public function testRemovePluginClassesIgnoresNotFoundClasses()
    {
        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $moduleFile) {
                PHPUnit_Framework_Assert::assertSame(array(RootModuleFileManagerImplTest::OTHER_PLUGIN_CLASS), $moduleFile->getPluginClasses());
            }));

        $this->rootModuleFile->addPluginClass(self::PLUGIN_CLASS);
        $this->rootModuleFile->addPluginClass(self::OTHER_PLUGIN_CLASS);

        $this->manager->removePluginClasses(Expr::in(array(self::PLUGIN_CLASS, 'Some\\Class')));

        $this->assertSame(array(self::OTHER_PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    public function testRemovePluginClassesDoesNothingIfNotFound()
    {
        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->rootModuleFile->addPluginClass(self::PLUGIN_CLASS);

        $this->manager->removePluginClasses(Expr::in(array(self::OTHER_PLUGIN_CLASS)));

        $this->assertSame(array(self::PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    public function testRemovePluginClassesRevertsIfSavingFails()
    {
        $this->rootModuleFile->addPluginClass(self::PLUGIN_CLASS);
        $this->rootModuleFile->addPluginClass(self::OTHER_PLUGIN_CLASS);

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->removePluginClasses(Expr::in(array(self::PLUGIN_CLASS, self::OTHER_PLUGIN_CLASS)));
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame(array(self::PLUGIN_CLASS, self::OTHER_PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    public function testClearPluginClasses()
    {
        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $moduleFile) {
                PHPUnit_Framework_Assert::assertSame(array(), $moduleFile->getPluginClasses());
            }));

        $this->rootModuleFile->addPluginClass(self::PLUGIN_CLASS);
        $this->rootModuleFile->addPluginClass(self::OTHER_PLUGIN_CLASS);

        $this->manager->clearPluginClasses();

        $this->assertSame(array(), $this->manager->getPluginClasses());
    }

    public function testClearPluginClassesDoesNothingIfNoneExist()
    {
        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->clearPluginClasses();

        $this->assertSame(array(), $this->manager->getPluginClasses());
    }

    public function testClearPluginClassesRevertsIfSavingFails()
    {
        $this->rootModuleFile->addPluginClass(self::PLUGIN_CLASS);
        $this->rootModuleFile->addPluginClass(self::OTHER_PLUGIN_CLASS);

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->clearPluginClasses();
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame(array(self::PLUGIN_CLASS, self::OTHER_PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    public function testGetPluginClasses()
    {
        $this->rootModuleFile->addPluginClass(self::PLUGIN_CLASS);
        $this->rootModuleFile->addPluginClass(self::OTHER_PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS, self::OTHER_PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    public function testFindPluginClasses()
    {
        $this->rootModuleFile->addPluginClass(self::PLUGIN_CLASS);
        $this->rootModuleFile->addPluginClass(self::OTHER_PLUGIN_CLASS);

        $expr1 = Expr::same(self::PLUGIN_CLASS);

        $expr2 = Expr::same('foo');

        $this->assertSame(array(self::PLUGIN_CLASS), $this->manager->findPluginClasses($expr1));
        $this->assertSame(array(), $this->manager->findPluginClasses($expr2));
    }

    public function testHasPluginClass()
    {
        $this->rootModuleFile->addPluginClass(self::PLUGIN_CLASS);

        $this->assertTrue($this->manager->hasPluginClass(self::PLUGIN_CLASS));
        $this->assertFalse($this->manager->hasPluginClass(self::OTHER_PLUGIN_CLASS));

        $this->assertFalse($this->manager->hasPluginClass('foobar'));
    }

    public function testHasPluginClasses()
    {
        $this->assertFalse($this->manager->hasPluginClasses());

        $this->rootModuleFile->addPluginClass(self::PLUGIN_CLASS);

        $this->assertTrue($this->manager->hasPluginClasses());
        $this->assertFalse($this->manager->hasPluginClasses(Expr::same(self::OTHER_PLUGIN_CLASS)));

        $this->rootModuleFile->addPluginClass(self::OTHER_PLUGIN_CLASS);

        $this->assertTrue($this->manager->hasPluginClasses(Expr::same(self::OTHER_PLUGIN_CLASS)));
    }

    public function testGetModuleName()
    {
        // Default name set in initContext()
        $this->assertSame('vendor/root', $this->manager->getModuleName());

        $this->rootModuleFile->setModuleName('vendor/module');

        $this->assertSame('vendor/module', $this->manager->getModuleName());
    }

    public function testSetModuleName()
    {
        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $moduleFile) {
                PHPUnit_Framework_Assert::assertSame('vendor/module', $moduleFile->getModuleName());
            }));

        $this->manager->setModuleName('vendor/module');

        $this->assertSame('vendor/module', $this->manager->getModuleName());
    }

    public function testSetModuleNameDoesNotSaveIfUnchanged()
    {
        $this->rootModuleFile->setModuleName('vendor/module');

        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->setModuleName('vendor/module');

        $this->assertSame('vendor/module', $this->manager->getModuleName());
    }

    public function testSetModuleNameRevertsIfSavingFails()
    {
        $this->rootModuleFile->setModuleName('vendor/old');

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->setModuleName('vendor/new');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('vendor/old', $this->manager->getModuleName());
    }

    public function testSetExtraKey()
    {
        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $moduleFile) {
                PHPUnit_Framework_Assert::assertSame('value', $moduleFile->getExtraKey('key'));
            }));

        $this->manager->setExtraKey('key', 'value');
    }

    public function testSetExtraKeyIgnoresDuplicates()
    {
        $this->rootModuleFile->setExtraKey('key', 'value');

        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->setExtraKey('key', 'value');
    }

    public function testSetExtraKeyRestoresPreviousValueIfSavingFails()
    {
        $this->rootModuleFile->setExtraKey('key', 'previous');

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->setExtraKey('key', 'value');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('previous', $this->rootModuleFile->getExtraKey('key'));
    }

    public function testSetExtraKeyRemovesPreviouslyUnsetKeysIfSavingFails()
    {
        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->setExtraKey('key', 'value');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertFalse($this->rootModuleFile->hasExtraKey('key'));
    }

    public function testSetExtraKeys()
    {
        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $moduleFile) {
                PHPUnit_Framework_Assert::assertSame(array(
                    'key1' => 'value1',
                    'key2' => 'value2',
                ), $moduleFile->getExtraKeys());
            }));

        $this->manager->setExtraKeys(array(
            'key1' => 'value1',
            'key2' => 'value2',
        ));
    }

    public function testSetExtraKeysIgnoresIfNoneChanged()
    {
        $this->rootModuleFile->setExtraKey('key1', 'value1');
        $this->rootModuleFile->setExtraKey('key2', 'value2');

        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->setExtraKeys(array(
            'key1' => 'value1',
            'key2' => 'value2',
        ));
    }

    public function testSetExtraKeysRestoresStateIfSavingFails()
    {
        $this->rootModuleFile->setExtraKey('key1', 'previous');

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->setExtraKeys(array(
                'key1' => 'value1',
                'key2' => 'value2',
            ));
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('previous', $this->rootModuleFile->getExtraKey('key1'));
        $this->assertFalse($this->rootModuleFile->hasExtraKey('key2'));
    }

    public function testRemoveExtraKey()
    {
        $this->rootModuleFile->setExtraKey('key1', 'value1');
        $this->rootModuleFile->setExtraKey('key2', 'value2');

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $moduleFile) {
                PHPUnit_Framework_Assert::assertSame(array(
                    'key2' => 'value2',
                ), $moduleFile->getExtraKeys());
            }));

        $this->manager->removeExtraKey('key1');
    }

    public function testRemoveExtraKeyIgnoresNonExisting()
    {
        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->removeExtraKey('foobar');
    }

    public function testRemoveExtraKeyRestoresValueIfSavingFails()
    {
        $this->rootModuleFile->setExtraKey('key1', 'previous');

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->removeExtraKey('key1');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('previous', $this->rootModuleFile->getExtraKey('key1'));
    }

    public function testRemoveExtraKeys()
    {
        $this->rootModuleFile->setExtraKey('key1', 'value1');
        $this->rootModuleFile->setExtraKey('key2', 'value2');
        $this->rootModuleFile->setExtraKey('key3', 'value3');

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $moduleFile) {
                PHPUnit_Framework_Assert::assertSame(array(
                    'key2' => 'value2',
                ), $moduleFile->getExtraKeys());
            }));

        $this->manager->removeExtraKeys(Expr::in(array('key1', 'key3')));
    }

    public function testRemoveExtraKeysIgnoresIfNoneRemoved()
    {
        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->removeExtraKeys(Expr::in(array('key1', 'key3')));
    }

    public function testRemoveExtraKeysRestoresPreviousValuesIfSavingFails()
    {
        $this->rootModuleFile->setExtraKey('key1', 'previous');

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->removeExtraKeys(Expr::in(array('key1', 'key2')));
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('previous', $this->rootModuleFile->getExtraKey('key1'));
        $this->assertFalse($this->rootModuleFile->hasExtraKey('key2'));
    }

    public function testClearExtraKeys()
    {
        $this->rootModuleFile->setExtraKey('key1', 'value1');
        $this->rootModuleFile->setExtraKey('key2', 'value2');

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $moduleFile) {
                PHPUnit_Framework_Assert::assertSame(array(), $moduleFile->getExtraKeys());
            }));

        $this->manager->clearExtraKeys();
    }

    public function testClearExtraKeysDoesNothingIfNoneSet()
    {
        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->clearExtraKeys();
    }

    public function testClearExtraKeyRestoresValuesIfSavingFails()
    {
        $this->rootModuleFile->setExtraKey('key1', 'value1');
        $this->rootModuleFile->setExtraKey('key2', 'value2');

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->clearExtraKeys();
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('value1', $this->rootModuleFile->getExtraKey('key1'));
        $this->assertSame('value2', $this->rootModuleFile->getExtraKey('key2'));
    }

    public function testHasExtraKey()
    {
        $this->assertFalse($this->manager->hasExtraKey('key'));
        $this->rootModuleFile->setExtraKey('key', 'value');
        $this->assertTrue($this->manager->hasExtraKey('key'));
    }

    public function testHasExtraKeys()
    {
        $this->assertFalse($this->manager->hasExtraKeys());

        $this->rootModuleFile->setExtraKey('key1', 'value');

        $this->assertTrue($this->manager->hasExtraKeys());
        $this->assertFalse($this->manager->hasExtraKeys(Expr::same('key2')));

        $this->rootModuleFile->setExtraKey('key2', 'value');

        $this->assertTrue($this->manager->hasExtraKeys(Expr::same('key2')));
    }

    public function testGetExtraKey()
    {
        $this->assertNull($this->manager->getExtraKey('key'));
        $this->assertSame('default', $this->manager->getExtraKey('key', 'default'));
        $this->rootModuleFile->setExtraKey('key', 'value');
        $this->assertSame('value', $this->manager->getExtraKey('key'));
    }

    public function testGetExtraKeys()
    {
        $this->assertSame(array(), $this->manager->getExtraKeys());
        $this->rootModuleFile->setExtraKey('key1', 'value1');
        $this->rootModuleFile->setExtraKey('key2', 'value2');
        $this->assertSame(array(
            'key1' => 'value1',
            'key2' => 'value2',
        ), $this->manager->getExtraKeys());
    }

    public function testFindExtraKeys()
    {
        $this->rootModuleFile->setExtraKey('key1', 'value1');
        $this->rootModuleFile->setExtraKey('key2', 'value2');

        $expr1 = Expr::same('key1');
        $expr2 = Expr::startsWith('key');
        $expr3 = Expr::same('foo');

        $this->assertSame(array('key1' => 'value1'), $this->manager->findExtraKeys($expr1));
        $this->assertSame(array('key1' => 'value1', 'key2' => 'value2'), $this->manager->findExtraKeys($expr2));
        $this->assertSame(array(), $this->manager->findExtraKeys($expr3));
    }

    public function testMigrate()
    {
        $this->rootModuleFile->setVersion('1.0');

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->willReturnCallback(function (RootModuleFile $rootModuleFile) {
                PHPUnit_Framework_Assert::assertSame('1.1', $rootModuleFile->getVersion());
            });

        $this->manager->migrate('1.1');

        $this->assertSame('1.1', $this->rootModuleFile->getVersion());
    }

    public function testMigrateRestoresVersionIfSavingFails()
    {
        $this->rootModuleFile->setVersion('1.0');

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->throwException(new TestException()));

        try {
            $this->manager->migrate('1.1');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('1.0', $this->rootModuleFile->getVersion());
    }
}
