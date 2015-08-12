<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Config;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Config\Config;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigTest extends PHPUnit_Framework_TestCase
{
    public function testGetRaw()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'puli-dir');

        $this->assertSame('puli-dir', $config->getRaw(Config::PULI_DIR));
    }

    public function testGetRawReturnsNullIfNotSet()
    {
        $config = new Config();

        $this->assertNull($config->getRaw(Config::PULI_DIR));
    }

    public function testGetRawWithCustomDefault()
    {
        $config = new Config();

        $this->assertSame('my-default', $config->getRaw(Config::PULI_DIR, 'my-default'));
    }

    public function testGetRawReturnsFallbackIfSet()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $config = new Config($baseConfig);

        $this->assertSame('my-puli-dir', $config->getRaw(Config::PULI_DIR));
    }

    public function testGetRawPassesCustomDefaultToFallbackConfig()
    {
        $baseConfig = new Config();
        $config = new Config($baseConfig);

        $this->assertSame('my-default', $config->getRaw(Config::PULI_DIR, 'my-default'));
    }

    public function testGetRawDoesNotReturnFallbackIfDisabled()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $config = new Config($baseConfig);

        $this->assertNull($config->getRaw(Config::PULI_DIR, null, false));
    }

    public function testGetRawDoesNotReplacePlaceholder()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'my-puli-dir');
        $config->set(Config::FACTORY_IN_FILE, '{$puli-dir}/ServiceRegistry.php');

        $this->assertSame('{$puli-dir}/ServiceRegistry.php', $config->getRaw(Config::FACTORY_IN_FILE));
    }

    /**
     * @expectedException \Puli\Manager\Api\Config\NoSuchConfigKeyException
     * @expectedExceptionMessage foo
     */
    public function testGetRawFailsIfInvalidKey()
    {
        $config = new Config();
        $config->getRaw('foo');
    }

    public function testGetRawCompositeKey()
    {
        $config = new Config();
        $config->set(Config::DISCOVERY_TYPE, 'my-type');
        $config->set(Config::DISCOVERY_STORE_TYPE, 'my-store-type');

        $this->assertSame(array(
            'type' => 'my-type',
            'store' => array(
                'type' => 'my-store-type',
            ),
        ), $config->getRaw(Config::DISCOVERY));
    }

    public function testGetRawCompositeKeyReturnsArrayIfNotSet()
    {
        $config = new Config();

        $this->assertSame(array(), $config->getRaw(Config::REPOSITORY));
    }

    public function testGetRawCompositeKeyWithCustomDefault()
    {
        $default = array('type' => 'my-type');

        $config = new Config();
        $config->set(Config::REPOSITORY_PATH, 'my-path');

        $this->assertSame(array(
            'type' => 'my-type',
            'path' => 'my-path',
        ), $config->getRaw(Config::REPOSITORY, $default));
    }

    public function testGetRawCompositeKeyIncludesFallbackKeys()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::REPOSITORY_TYPE, 'fallback-type');
        $baseConfig->set(Config::REPOSITORY_PATH, 'fallback-path');

        $config = new Config($baseConfig);
        $config->set(Config::REPOSITORY_PATH, 'my-path');

        $this->assertSame(array(
            'type' => 'fallback-type',
            'path' => 'my-path',
        ), $config->getRaw(Config::REPOSITORY));
    }

    public function testGetRawCompositeKeyIncludesFallbackKeysOfMultipleLevels()
    {
        $baseBaseConfig = new Config();
        $baseBaseConfig->set(Config::DISCOVERY_STORE_TYPE, 'my-store');

        $baseConfig = new Config($baseBaseConfig);
        $baseConfig->set(Config::DISCOVERY_TYPE, 'fallback-type');

        $config = new Config($baseConfig);
        $config->set(Config::DISCOVERY_STORE_PATH, 'my-path');

        $this->assertSame(array(
            'store' => array(
                'type' => 'my-store',
                'path' => 'my-path',
            ),
            'type' => 'fallback-type',
        ), $config->getRaw(Config::DISCOVERY));
    }

    public function testGetRawCompositeKeyPassesDefaultToFallback()
    {
        $default = array('type' => 'my-type');

        $baseConfig = new Config();
        $baseConfig->set(Config::REPOSITORY_PATH, 'my-path');
        $config = new Config($baseConfig);

        $this->assertSame(array(
            'type' => 'my-type',
            'path' => 'my-path',
        ), $config->getRaw(Config::REPOSITORY, $default));
    }

    public function testGetRawCompositeKeyDoesNotIncludeFallbackKeysIfDisabled()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::REPOSITORY_TYPE, 'fallback-type');
        $baseConfig->set(Config::REPOSITORY_PATH, 'fallback-path');

        $config = new Config($baseConfig);
        $config->set(Config::REPOSITORY_PATH, 'my-path');

        $this->assertSame(array(
            'path' => 'my-path',
        ), $config->getRaw(Config::REPOSITORY, null, false));
    }

    public function testGetRawCompositeKeyDoesNotReplacePlaceholders()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'puli-dir');
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            'path' => '{$puli-dir}/my-path',
        ), $config->getRaw(Config::REPOSITORY));
    }

    public function testGet()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
    }

    public function testGetReturnsNullIfNotSet()
    {
        $config = new Config();

        $this->assertNull($config->get(Config::PULI_DIR));
    }

    public function testGetWithFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'fallback');
        $config = new Config($baseConfig);
        $config->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
    }

    public function testGetReturnsFallbackIfSet()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $config = new Config($baseConfig);

        $this->assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
    }

    public function testGetDoesNotReturnFallbackIfDisabled()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $config = new Config($baseConfig);

        $this->assertNull($config->get(Config::PULI_DIR, null, false));
    }

    public function testGetWithCustomDefaultValue()
    {
        $config = new Config();

        $this->assertSame('my-default', $config->get(Config::PULI_DIR, 'my-default'));
    }

    public function testGetReplacesPlaceholder()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'my-puli-dir');
        $config->set(Config::FACTORY_IN_FILE, '{$puli-dir}/ServiceRegistry.php');

        $this->assertSame('my-puli-dir/ServiceRegistry.php', $config->get(Config::FACTORY_IN_FILE));
    }

    public function testGetReplacesPlaceholderDefinedInDefaultConfig()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::FACTORY_IN_FILE, '{$puli-dir}/ServiceRegistry.php');
        $config = new Config($baseConfig);
        $config->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir/ServiceRegistry.php', $config->get(Config::FACTORY_IN_FILE));
    }

    public function testGetReplacesPlaceholderSetInDefaultConfig()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $config = new Config($baseConfig);
        $config->set(Config::FACTORY_IN_FILE, '{$puli-dir}/ServiceRegistry.php');

        $this->assertSame('my-puli-dir/ServiceRegistry.php', $config->get(Config::FACTORY_IN_FILE));
    }

    public function testGetDoesNotUseFallbackPlaceholderIfFallbackDisabled()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $config = new Config($baseConfig);
        $config->set(Config::FACTORY_IN_FILE, '{$puli-dir}/ServiceRegistry.php');

        $this->assertSame('/ServiceRegistry.php', $config->get(Config::FACTORY_IN_FILE, null, false));
    }

    /**
     * @expectedException \Puli\Manager\Api\Config\NoSuchConfigKeyException
     * @expectedExceptionMessage foo
     */
    public function testGetFailsIfInvalidKey()
    {
        $config = new Config();
        $config->get('foo');
    }

    public function testGetCompositeKey()
    {
        $config = new Config();
        $config->set(Config::DISCOVERY_TYPE, 'my-type');
        $config->set(Config::DISCOVERY_STORE_TYPE, 'my-store-type');

        $this->assertSame(array(
            'type' => 'my-type',
            'store' => array(
                'type' => 'my-store-type',
            ),
        ), $config->get(Config::DISCOVERY));
    }

    public function testGetCompositeKeyReturnsArrayIfNotSet()
    {
        $config = new Config();

        $this->assertSame(array(), $config->get(Config::REPOSITORY));
    }

    public function testGetCompositeKeyWithCustomDefault()
    {
        $default = array('type' => 'my-type');

        $config = new Config();
        $config->set(Config::REPOSITORY_PATH, 'my-path');

        $this->assertSame(array(
            'type' => 'my-type',
            'path' => 'my-path',
        ), $config->get(Config::REPOSITORY, $default));
    }

    public function testGetCompositeKeyIncludesFallbackKeys()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::REPOSITORY_TYPE, 'my-type');

        $config = new Config($baseConfig);
        $config->set(Config::REPOSITORY_PATH, 'my-path');

        $this->assertSame(array(
            'type' => 'my-type',
            'path' => 'my-path',
        ), $config->get(Config::REPOSITORY));
    }

    public function testGetCompositeKeyDoesNotIncludeFallbackKeysIfDisabled()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::REPOSITORY_TYPE, 'my-type');

        $config = new Config($baseConfig);
        $config->set(Config::REPOSITORY_PATH, 'my-path');

        $this->assertSame(array(
            'type' => 'my-type',
            'path' => 'my-path',
        ), $config->get(Config::REPOSITORY));
    }

    public function testGetCompositeKeyReplacesPlaceholders()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'puli-dir');
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            'path' => 'puli-dir/my-path',
        ), $config->get(Config::REPOSITORY));
    }

    public function testGetCompositeKeyUsesFallbackPlaceholders()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'puli-dir');

        $config = new Config($baseConfig);
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            'path' => 'puli-dir/my-path',
        ), $config->get(Config::REPOSITORY));
    }

    public function testGetCompositeKeyDoesNotUseFallbackPlaceholdersIfDisabled()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'puli-dir');

        $config = new Config($baseConfig);
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            'path' => '/my-path',
        ), $config->get(Config::REPOSITORY, null, false));
    }

    public function testContains()
    {
        $baseConfig = new Config();
        $config = new Config($baseConfig);

        $this->assertFalse($config->contains(Config::PULI_DIR));

        $baseConfig->set(Config::PULI_DIR, 'puli-dir');

        $this->assertTrue($config->contains(Config::PULI_DIR));

        $config->set(Config::PULI_DIR, 'puli-dir');

        $this->assertTrue($config->contains(Config::PULI_DIR));
    }

    public function testContainsWithoutFallback()
    {
        $baseConfig = new Config();
        $config = new Config($baseConfig);

        $this->assertFalse($config->contains(Config::PULI_DIR, false));

        $baseConfig->set(Config::PULI_DIR, 'puli-dir');

        $this->assertFalse($config->contains(Config::PULI_DIR, false));

        $config->set(Config::PULI_DIR, 'puli-dir');

        $this->assertTrue($config->contains(Config::PULI_DIR, false));
    }

    /**
     * @expectedException \Puli\Manager\Api\Config\NoSuchConfigKeyException
     * @expectedExceptionMessage foo
     */
    public function testContainsFailsIfInvalidKey()
    {
        $config = new Config();
        $config->contains('foo');
    }

    public function testContainsCompositeKey()
    {
        $config = new Config();

        $this->assertFalse($config->contains(Config::DISCOVERY));
        $this->assertFalse($config->contains(Config::DISCOVERY_STORE));
        $this->assertFalse($config->contains(Config::DISCOVERY_STORE_TYPE));

        $config->set(Config::DISCOVERY, array(
            'store' => array(
                'type' => 'my-store-type',
            ),
        ));

        $this->assertTrue($config->contains(Config::DISCOVERY));
        $this->assertTrue($config->contains(Config::DISCOVERY_STORE));
        $this->assertTrue($config->contains(Config::DISCOVERY_STORE_TYPE));
    }

    public function testContainsCompositeKeyWithFallback()
    {
        $baseConfig = new Config();
        $config = new Config($baseConfig);

        $this->assertFalse($config->contains(Config::DISCOVERY));
        $this->assertFalse($config->contains(Config::DISCOVERY_STORE));
        $this->assertFalse($config->contains(Config::DISCOVERY_STORE_TYPE));

        $baseConfig->set(Config::DISCOVERY, array(
            'store' => array(
                'type' => 'my-store-type',
            ),
        ));

        $this->assertTrue($config->contains(Config::DISCOVERY));
        $this->assertTrue($config->contains(Config::DISCOVERY_STORE));
        $this->assertTrue($config->contains(Config::DISCOVERY_STORE_TYPE));
    }

    public function testContainsCompositeKeyWithoutFallback()
    {
        $baseConfig = new Config();
        $config = new Config($baseConfig);

        $this->assertFalse($config->contains(Config::DISCOVERY, false));
        $this->assertFalse($config->contains(Config::DISCOVERY_STORE, false));
        $this->assertFalse($config->contains(Config::DISCOVERY_STORE_TYPE, false));

        $baseConfig->set(Config::DISCOVERY, array(
            'store' => array(
                'type' => 'my-store-type',
            ),
        ));

        $this->assertFalse($config->contains(Config::DISCOVERY, false));
        $this->assertFalse($config->contains(Config::DISCOVERY_STORE, false));
        $this->assertFalse($config->contains(Config::DISCOVERY_STORE_TYPE, false));
    }

    public function testSetCompositeKey()
    {
        $config = new Config();
        $config->set(Config::DISCOVERY, array(
            'type' => 'my-type',
            'store' => array(
                'type' => 'my-store-type',
            ),
        ));

        $this->assertSame('my-type', $config->get(Config::DISCOVERY_TYPE));
        $this->assertSame('my-store-type', $config->get(Config::DISCOVERY_STORE_TYPE));
        $this->assertSame(array(
            'type' => 'my-type',
            'store' => array(
                'type' => 'my-store-type',
            ),
        ), $config->get(Config::DISCOVERY));
    }

    public function testSetCompositeKeyRemovesPreviouslySetKeys()
    {
        $config = new Config();
        $config->set(Config::REPOSITORY_TYPE, 'my-type');
        $config->set(Config::REPOSITORY, array(
            'path' => 'my-path',
        ));

        $this->assertSame(array(
            'path' => 'my-path',
        ), $config->get(Config::REPOSITORY));
    }

    /**
     * @expectedException \Puli\Manager\Api\Config\NoSuchConfigKeyException
     * @expectedExceptionMessage foo
     */
    public function testSetFailsIfInvalidKey()
    {
        $config = new Config();
        $config->set('foo', 'bar');
    }

    /**
     * @dataProvider getNotNullKeys
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testSetFailsIfValueIsNull($key)
    {
        $config = new Config();
        $config->set($key, null);
    }

    /**
     * @dataProvider getStringKeys
     */
    public function testStringKeys($key)
    {
        $config = new Config();
        $config->set($key, 'string');

        $this->assertSame('string', $config->get($key));
    }

    /**
     * @dataProvider getStringKeys
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testSetFailsIfValueIsNotString($key)
    {
        $config = new Config();
        $config->set($key, 12345);
    }

    /**
     * @dataProvider getNonEmptyKeys
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testSetFailsIfValueIsEmptyString($key)
    {
        $config = new Config();
        $config->set($key, '');
    }

    /**
     * @dataProvider getNullableKeys
     */
    public function testNullValues($key)
    {
        $config = new Config();
        $config->set($key, null);

        $this->assertNull($config->get($key));
    }

    /**
     * @dataProvider getBooleanKeys
     */
    public function testBooleanValues($key)
    {
        $config = new Config();
        $config->set($key, true);

        $this->assertTrue($config->get($key));
    }

    /**
     * @dataProvider getBooleanKeys
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testSetFailsIfValueIsNotBoolean($key)
    {
        $config = new Config();
        $config->set($key, 'true');
    }

    public function testMerge()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'puli-dir');
        $config->set(Config::FACTORY_IN_CLASS, 'Puli\ServiceRegistry');
        $config->merge(array(
            Config::FACTORY_IN_CLASS => 'My\ServiceRegistry',
            Config::FACTORY_IN_FILE => 'repo-file.php',
        ));

        $this->assertSame('puli-dir', $config->get(Config::PULI_DIR));
        $this->assertSame('My\ServiceRegistry', $config->get(Config::FACTORY_IN_CLASS));
        $this->assertSame('repo-file.php', $config->get(Config::FACTORY_IN_FILE));
    }

    public function testReplace()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'puli-dir');
        $config->set(Config::FACTORY_IN_CLASS, 'Puli\ServiceRegistry');
        $config->replace(array(
            Config::FACTORY_IN_CLASS => 'My\ServiceRegistry',
            Config::FACTORY_IN_FILE => 'repo-file.php',
        ));

        $this->assertNull($config->get(Config::PULI_DIR));
        $this->assertSame('My\ServiceRegistry', $config->get(Config::FACTORY_IN_CLASS));
        $this->assertSame('repo-file.php', $config->get(Config::FACTORY_IN_FILE));
    }

    public function testRemove()
    {
        $config = new Config();
        $config->set(Config::FACTORY_IN_FILE, 'ServiceRegistry.php');
        $config->set(Config::FACTORY_IN_CLASS, 'Puli\ServiceRegistry');
        $config->remove(Config::FACTORY_IN_CLASS);

        $this->assertSame('ServiceRegistry.php', $config->get(Config::FACTORY_IN_FILE));
        $this->assertNull($config->get(Config::FACTORY_IN_CLASS));
    }

    public function testRemoveCompositeKey()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'puli-dir');
        $config->set(Config::REPOSITORY_TYPE, 'my-type');
        $config->remove(Config::REPOSITORY);

        $this->assertSame('puli-dir', $config->get(Config::PULI_DIR));
        $this->assertSame(array(), $config->get(Config::REPOSITORY));
        $this->assertNull($config->get(Config::REPOSITORY_TYPE));
    }

    /**
     * @expectedException \Puli\Manager\Api\Config\NoSuchConfigKeyException
     * @expectedExceptionMessage foo
     */
    public function testRemoveFailsIfInvalidKey()
    {
        $config = new Config();
        $config->remove('foo');
    }

    public function testClear()
    {
        $config = new Config();
        $config->set(Config::FACTORY_IN_FILE, 'ServiceRegistry.php');
        $config->set(Config::FACTORY_IN_CLASS, 'Puli\ServiceRegistry');
        $config->clear();

        $this->assertNull($config->get(Config::FACTORY_IN_FILE));
        $this->assertNull($config->get(Config::FACTORY_IN_CLASS));
    }

    public function testGetReturnsFallbackAfterRemove()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::FACTORY_IN_CLASS, 'Fallback\ServiceRegistry');
        $config = new Config($baseConfig);
        $config->set(Config::FACTORY_IN_FILE, 'ServiceRegistry.php');
        $config->set(Config::FACTORY_IN_CLASS, 'Puli\ServiceRegistry');
        $config->remove(Config::FACTORY_IN_CLASS);

        $this->assertSame('ServiceRegistry.php', $config->get(Config::FACTORY_IN_FILE));
        $this->assertSame('Fallback\ServiceRegistry', $config->get(Config::FACTORY_IN_CLASS));
    }

    public function testToRawArray()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'my-puli-dir');
        $config->set(Config::REPOSITORY_TYPE, 'my-type');
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPOSITORY => array(
                'type' => 'my-type',
                'path' => '{$puli-dir}/my-path',
            ),
        ), $config->toRawArray());
    }

    public function testToRawArrayWithFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPOSITORY_TYPE, 'my-type');

        $config = new Config($baseConfig);
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPOSITORY => array(
                'type' => 'my-type',
                'path' => '{$puli-dir}/my-path',
            ),
        ), $config->toRawArray());
    }

    public function testToRawArrayWithoutFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPOSITORY_TYPE, 'my-type');

        $config = new Config($baseConfig);
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            Config::REPOSITORY => array(
                'path' => '{$puli-dir}/my-path',
            ),
        ), $config->toRawArray(false));
    }

    public function testToFlatRawArray()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'my-puli-dir');
        $config->set(Config::REPOSITORY_TYPE, 'my-type');
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPOSITORY_TYPE => 'my-type',
            Config::REPOSITORY_PATH => '{$puli-dir}/my-path',
        ), $config->toFlatRawArray());
    }

    public function testToFlatRawArrayWithFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPOSITORY_TYPE, 'my-type');
        $config = new Config($baseConfig);
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPOSITORY_TYPE => 'my-type',
            Config::REPOSITORY_PATH => '{$puli-dir}/my-path',
        ), $config->toFlatRawArray());
    }

    public function testToFlatRawArrayWithoutFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPOSITORY_TYPE, 'my-type');
        $config = new Config($baseConfig);
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            Config::REPOSITORY_PATH => '{$puli-dir}/my-path',
        ), $config->toFlatRawArray(false));
    }

    public function testToArray()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'my-puli-dir');
        $config->set(Config::REPOSITORY_TYPE, 'my-type');
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPOSITORY => array(
                'type' => 'my-type',
                'path' => 'my-puli-dir/my-path',
            ),
        ), $config->toArray());
    }

    public function testToArrayWithFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPOSITORY_TYPE, 'my-type');

        $config = new Config($baseConfig);
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPOSITORY => array(
                'type' => 'my-type',
                'path' => 'my-puli-dir/my-path',
            ),
        ), $config->toArray());
    }

    public function testToArrayWithoutFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPOSITORY_TYPE, 'my-type');

        $config = new Config($baseConfig);
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            Config::REPOSITORY => array(
                'path' => '/my-path',
            ),
        ), $config->toArray(false));
    }

    public function testToFlatArray()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'my-puli-dir');
        $config->set(Config::REPOSITORY_TYPE, 'my-type');
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPOSITORY_TYPE => 'my-type',
            Config::REPOSITORY_PATH => 'my-puli-dir/my-path',
        ), $config->toFlatArray());
    }

    public function testToFlatArrayWithFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPOSITORY_TYPE, 'my-type');
        $config = new Config($baseConfig);
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPOSITORY_TYPE => 'my-type',
            Config::REPOSITORY_PATH => 'my-puli-dir/my-path',
        ), $config->toFlatArray());
    }

    public function testToFlatArrayWithoutFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPOSITORY_TYPE, 'my-type');
        $config = new Config($baseConfig);
        $config->set(Config::REPOSITORY_PATH, '{$puli-dir}/my-path');

        $this->assertSame(array(
            Config::REPOSITORY_PATH => '/my-path',
        ), $config->toFlatArray(false));
    }

    public function testIsEmpty()
    {
        $config = new Config();

        $this->assertTrue($config->isEmpty());

        $config->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertFalse($config->isEmpty());
    }

    public function testIsEmptyWithFallback()
    {
        $baseConfig = new Config();
        $config = new Config($baseConfig);

        $this->assertTrue($config->isEmpty());

        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertFalse($config->isEmpty());

        $config->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertFalse($config->isEmpty());
    }

    public function testIsEmptyWithoutFallback()
    {
        $baseConfig = new Config();
        $config = new Config($baseConfig);

        $this->assertTrue($config->isEmpty(false));

        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertTrue($config->isEmpty(false));

        $config->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertFalse($config->isEmpty(false));
    }

    public function getNotNullKeys()
    {
        return array(
            array(Config::PULI_DIR),
            array(Config::FACTORY_IN_CLASS),
            array(Config::FACTORY_IN_FILE),
            array(Config::REPOSITORY_STORE_BUCKET),
            array(Config::DISCOVERY_STORE_BUCKET),
        );
    }

    public function getNullableKeys()
    {
        return array(
            array(Config::BOOTSTRAP_FILE),
            array(Config::REPOSITORY_STORE_TYPE),
            array(Config::DISCOVERY_STORE_TYPE),
        );
    }

    public function getNonEmptyKeys()
    {
        return array(
            array(Config::PULI_DIR),
            array(Config::BOOTSTRAP_FILE),
            array(Config::FACTORY_IN_CLASS),
            array(Config::FACTORY_IN_FILE),
            array(Config::REPOSITORY_STORE_BUCKET),
            array(Config::DISCOVERY_STORE_BUCKET),
        );
    }

    public function getStringKeys()
    {
        return array(
            array(Config::PULI_DIR),
            array(Config::BOOTSTRAP_FILE),
            array(Config::FACTORY_IN_CLASS),
            array(Config::FACTORY_IN_FILE),
            array(Config::REPOSITORY_STORE_BUCKET),
            array(Config::DISCOVERY_STORE_BUCKET),
        );
    }

    public function getBooleanKeys()
    {
        return array(
            array(Config::FACTORY_AUTO_GENERATE),
            array(Config::REPOSITORY_SYMLINK),
            array(Config::REPOSITORY_STORE_CACHE),
            array(Config::REPOSITORY_OPTIMIZE),
            array(Config::DISCOVERY_STORE_CACHE),
        );
    }
}
