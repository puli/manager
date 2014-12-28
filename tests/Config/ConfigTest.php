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

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Config\Config;

/**
 * @since  1.0
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
        $config->set(Config::REGISTRY_FILE, '{$puli-dir}/ServiceRegistry.php');

        $this->assertSame('{$puli-dir}/ServiceRegistry.php', $config->getRaw(Config::REGISTRY_FILE));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Config\NoSuchConfigKeyException
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
        $config->set(Config::REPO_TYPE, 'my-type');
        $config->set(Config::REPO_STORAGE_DIR, 'my-storage-dir');

        $this->assertSame(array(
            'type' => 'my-type',
            'storage-dir' => 'my-storage-dir',
        ), $config->getRaw(Config::REPO));
    }

    public function testGetRawCompositeKeyReturnsArrayIfNotSet()
    {
        $config = new Config();

        $this->assertSame(array(), $config->getRaw(Config::REPO));
    }

    public function testGetRawCompositeKeyWithCustomDefault()
    {
        $default = array('type' => 'my-type');

        $config = new Config();
        $config->set(Config::REPO_STORAGE_DIR, 'my-storage-dir');

        $this->assertSame(array(
            'type' => 'my-type',
            'storage-dir' => 'my-storage-dir',
        ), $config->getRaw(Config::REPO, $default));
    }

    public function testGetRawCompositeKeyIncludesFallbackKeys()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::REPO_TYPE, 'fallback-type');
        $baseConfig->set(Config::REPO_STORAGE_DIR, 'fallback-storage-dir');

        $config = new Config($baseConfig);
        $config->set(Config::REPO_STORAGE_DIR, 'my-storage-dir');

        $this->assertSame(array(
            'type' => 'fallback-type',
            'storage-dir' => 'my-storage-dir',
        ), $config->getRaw(Config::REPO));
    }

    public function testGetRawCompositeKeyPassesDefaultToFallback()
    {
        $default = array('type' => 'my-type');

        $baseConfig = new Config();
        $baseConfig->set(Config::REPO_STORAGE_DIR, 'my-storage-dir');
        $config = new Config($baseConfig);

        $this->assertSame(array(
            'type' => 'my-type',
            'storage-dir' => 'my-storage-dir',
        ), $config->getRaw(Config::REPO, $default));
    }

    public function testGetRawCompositeKeyDoesNotIncludeFallbackKeysIfDisabled()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::REPO_TYPE, 'fallback-type');
        $baseConfig->set(Config::REPO_STORAGE_DIR, 'fallback-storage-dir');

        $config = new Config($baseConfig);
        $config->set(Config::REPO_STORAGE_DIR, 'my-storage-dir');

        $this->assertSame(array(
            'storage-dir' => 'my-storage-dir',
        ), $config->getRaw(Config::REPO, null, false));
    }

    public function testGetRawCompositeKeyDoesNotReplacePlaceholders()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'puli-dir');
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            'storage-dir' => '{$puli-dir}/my-storage-dir',
        ), $config->getRaw(Config::REPO));
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
        $config->set(Config::REGISTRY_FILE, '{$puli-dir}/ServiceRegistry.php');

        $this->assertSame('my-puli-dir/ServiceRegistry.php', $config->get(Config::REGISTRY_FILE));
    }

    public function testGetReplacesPlaceholderDefinedInDefaultConfig()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::REGISTRY_FILE, '{$puli-dir}/ServiceRegistry.php');
        $config = new Config($baseConfig);
        $config->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir/ServiceRegistry.php', $config->get(Config::REGISTRY_FILE));
    }

    public function testGetReplacesPlaceholderSetInDefaultConfig()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $config = new Config($baseConfig);
        $config->set(Config::REGISTRY_FILE, '{$puli-dir}/ServiceRegistry.php');

        $this->assertSame('my-puli-dir/ServiceRegistry.php', $config->get(Config::REGISTRY_FILE));
    }

    public function testGetDoesNotUseFallbackPlaceholderIfFallbackDisabled()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $config = new Config($baseConfig);
        $config->set(Config::REGISTRY_FILE, '{$puli-dir}/ServiceRegistry.php');

        $this->assertSame('/ServiceRegistry.php', $config->get(Config::REGISTRY_FILE, null, false));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Config\NoSuchConfigKeyException
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
        $config->set(Config::REPO_TYPE, 'my-type');
        $config->set(Config::REPO_STORAGE_DIR, 'my-storage-dir');

        $this->assertSame(array(
            'type' => 'my-type',
            'storage-dir' => 'my-storage-dir',
        ), $config->get(Config::REPO));
    }

    public function testGetCompositeKeyReturnsArrayIfNotSet()
    {
        $config = new Config();

        $this->assertSame(array(), $config->get(Config::REPO));
    }

    public function testGetCompositeKeyWithCustomDefault()
    {
        $default = array('type' => 'my-type');

        $config = new Config();
        $config->set(Config::REPO_STORAGE_DIR, 'my-storage-dir');

        $this->assertSame(array(
            'type' => 'my-type',
            'storage-dir' => 'my-storage-dir',
        ), $config->get(Config::REPO, $default));
    }

    public function testGetCompositeKeyIncludesFallbackKeys()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::REPO_TYPE, 'my-type');

        $config = new Config($baseConfig);
        $config->set(Config::REPO_STORAGE_DIR, 'my-storage-dir');

        $this->assertSame(array(
            'type' => 'my-type',
            'storage-dir' => 'my-storage-dir',
        ), $config->get(Config::REPO));
    }

    public function testGetCompositeKeyDoesNotIncludeFallbackKeysIfDisabled()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::REPO_TYPE, 'my-type');

        $config = new Config($baseConfig);
        $config->set(Config::REPO_STORAGE_DIR, 'my-storage-dir');

        $this->assertSame(array(
            'type' => 'my-type',
            'storage-dir' => 'my-storage-dir',
        ), $config->get(Config::REPO));
    }

    public function testGetCompositeKeyReplacesPlaceholders()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'puli-dir');
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            'storage-dir' => 'puli-dir/my-storage-dir',
        ), $config->get(Config::REPO));
    }

    public function testGetCompositeKeyUsesFallbackPlaceholders()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'puli-dir');

        $config = new Config($baseConfig);
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            'storage-dir' => 'puli-dir/my-storage-dir',
        ), $config->get(Config::REPO));
    }

    public function testGetCompositeKeyDoesNotUseFallbackPlaceholdersIfDisabled()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'puli-dir');

        $config = new Config($baseConfig);
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            'storage-dir' => '/my-storage-dir',
        ), $config->get(Config::REPO, null, false));
    }

    public function testSetCompositeKey()
    {
        $config = new Config();
        $config->set(Config::REPO, array(
            'type' => 'my-type',
            'storage-dir' => 'my-storage-dir',
        ));

        $this->assertSame('my-type', $config->get(Config::REPO_TYPE));
        $this->assertSame('my-storage-dir', $config->get(Config::REPO_STORAGE_DIR));
        $this->assertSame(array(
            'type' => 'my-type',
            'storage-dir' => 'my-storage-dir',
        ), $config->get(Config::REPO));
    }

    public function testSetCompositeKeyRemovesPreviouslySetKeys()
    {
        $config = new Config();
        $config->set(Config::REPO_TYPE, 'my-type');
        $config->set(Config::REPO, array(
            'storage-dir' => 'my-storage-dir',
        ));

        $this->assertSame(array(
            'storage-dir' => 'my-storage-dir',
        ), $config->get(Config::REPO));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Config\NoSuchConfigKeyException
     * @expectedExceptionMessage foo
     */
    public function testSetFailsIfInvalidKey()
    {
        $config = new Config();
        $config->set('foo', 'bar');
    }

    /**
     * @dataProvider getNotNullKeys
     * @expectedException \Puli\RepositoryManager\InvalidConfigException
     */
    public function testSetFailsIfValueIsNull($key)
    {
        $config = new Config();
        $config->set($key, null);
    }

    /**
     * @dataProvider getStringKeys
     * @expectedException \Puli\RepositoryManager\InvalidConfigException
     */
    public function testSetFailsIfValueIsNotString($key)
    {
        $config = new Config();
        $config->set($key, 12345);
    }

    /**
     * @dataProvider getNonEmptyKeys
     * @expectedException \Puli\RepositoryManager\InvalidConfigException
     */
    public function testSetFailsIfValueIsEmptyString($key)
    {
        $config = new Config();
        $config->set($key, '');
    }

    public function testMerge()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'puli-dir');
        $config->set(Config::REGISTRY_CLASS, 'Puli\ServiceRegistry');
        $config->merge(array(
            Config::REGISTRY_CLASS => 'My\ServiceRegistry',
            Config::REGISTRY_FILE => 'repo-file.php',
        ));

        $this->assertSame('puli-dir', $config->get(Config::PULI_DIR));
        $this->assertSame('My\ServiceRegistry', $config->get(Config::REGISTRY_CLASS));
        $this->assertSame('repo-file.php', $config->get(Config::REGISTRY_FILE));
    }

    public function testRemove()
    {
        $config = new Config();
        $config->set(Config::REGISTRY_FILE, 'ServiceRegistry.php');
        $config->set(Config::REGISTRY_CLASS, 'Puli\ServiceRegistry');
        $config->remove(Config::REGISTRY_CLASS);

        $this->assertSame('ServiceRegistry.php', $config->get(Config::REGISTRY_FILE));
        $this->assertNull($config->get(Config::REGISTRY_CLASS));
    }

    public function testRemoveCompositeKey()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'puli-dir');
        $config->set(Config::REPO_TYPE, 'my-type');
        $config->remove(Config::REPO);

        $this->assertSame('puli-dir', $config->get(Config::PULI_DIR));
        $this->assertSame(array(), $config->get(Config::REPO));
        $this->assertNull($config->get(Config::REPO_TYPE));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Config\NoSuchConfigKeyException
     * @expectedExceptionMessage foo
     */
    public function testRemoveFailsIfInvalidKey()
    {
        $config = new Config();
        $config->remove('foo');
    }

    public function testGetReturnsFallbackAfterRemove()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::REGISTRY_CLASS, 'Fallback\ServiceRegistry');
        $config = new Config($baseConfig);
        $config->set(Config::REGISTRY_FILE, 'ServiceRegistry.php');
        $config->set(Config::REGISTRY_CLASS, 'Puli\ServiceRegistry');
        $config->remove(Config::REGISTRY_CLASS);

        $this->assertSame('ServiceRegistry.php', $config->get(Config::REGISTRY_FILE));
        $this->assertSame('Fallback\ServiceRegistry', $config->get(Config::REGISTRY_CLASS));
    }

    public function testToRawArray()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'my-puli-dir');
        $config->set(Config::REPO_TYPE, 'my-type');
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPO_TYPE => 'my-type',
            Config::REPO_STORAGE_DIR => '{$puli-dir}/my-storage-dir',
        ), $config->toRawArray());
    }

    public function testToRawArrayWithFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPO_TYPE, 'my-type');
        $config = new Config($baseConfig);
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPO_TYPE => 'my-type',
            Config::REPO_STORAGE_DIR => '{$puli-dir}/my-storage-dir',
        ), $config->toRawArray());
    }

    public function testToRawArrayWithoutFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPO_TYPE, 'my-type');
        $config = new Config($baseConfig);
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            Config::REPO_STORAGE_DIR => '{$puli-dir}/my-storage-dir',
        ), $config->toRawArray(false));
    }

    public function testToRawNestedArray()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'my-puli-dir');
        $config->set(Config::REPO_TYPE, 'my-type');
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPO => array(
                'type' => 'my-type',
                'storage-dir' => '{$puli-dir}/my-storage-dir',
            )
        ), $config->toRawNestedArray());
    }

    public function testToRawNestedArrayWithFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPO_TYPE, 'my-type');

        $config = new Config($baseConfig);
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPO => array(
                'type' => 'my-type',
                'storage-dir' => '{$puli-dir}/my-storage-dir',
            )
        ), $config->toRawNestedArray());
    }

    public function testToRawNestedArrayWithoutFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPO_TYPE, 'my-type');

        $config = new Config($baseConfig);
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            Config::REPO => array(
                'storage-dir' => '{$puli-dir}/my-storage-dir',
            )
        ), $config->toRawNestedArray(false));
    }


    public function testToArray()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'my-puli-dir');
        $config->set(Config::REPO_TYPE, 'my-type');
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPO_TYPE => 'my-type',
            Config::REPO_STORAGE_DIR => 'my-puli-dir/my-storage-dir',
        ), $config->toArray());
    }

    public function testToArrayWithFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPO_TYPE, 'my-type');
        $config = new Config($baseConfig);
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPO_TYPE => 'my-type',
            Config::REPO_STORAGE_DIR => 'my-puli-dir/my-storage-dir',
        ), $config->toArray());
    }

    public function testToArrayWithoutFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPO_TYPE, 'my-type');
        $config = new Config($baseConfig);
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            Config::REPO_STORAGE_DIR => '/my-storage-dir',
        ), $config->toArray(false));
    }
    public function testToNestedArray()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'my-puli-dir');
        $config->set(Config::REPO_TYPE, 'my-type');
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPO => array(
                'type' => 'my-type',
                'storage-dir' => 'my-puli-dir/my-storage-dir',
            )
        ), $config->toNestedArray());
    }

    public function testToNestedArrayWithFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPO_TYPE, 'my-type');

        $config = new Config($baseConfig);
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::REPO => array(
                'type' => 'my-type',
                'storage-dir' => 'my-puli-dir/my-storage-dir',
            )
        ), $config->toNestedArray());
    }

    public function testToNestedArrayWithoutFallback()
    {
        $baseConfig = new Config();
        $baseConfig->set(Config::PULI_DIR, 'my-puli-dir');
        $baseConfig->set(Config::REPO_TYPE, 'my-type');

        $config = new Config($baseConfig);
        $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/my-storage-dir');

        $this->assertSame(array(
            Config::REPO => array(
                'storage-dir' => '/my-storage-dir',
            )
        ), $config->toNestedArray(false));
    }

    public function getNotNullKeys()
    {
        return array(
            array(Config::PULI_DIR),
            array(Config::REGISTRY_CLASS),
            array(Config::REGISTRY_FILE),
        );
    }

    public function getNonEmptyKeys()
    {
        return array(
            array(Config::PULI_DIR),
            array(Config::REGISTRY_CLASS),
            array(Config::REGISTRY_FILE),
        );
    }

    public function getStringKeys()
    {
        return array(
            array(Config::PULI_DIR),
            array(Config::REGISTRY_CLASS),
            array(Config::REGISTRY_FILE),
        );
    }
}
