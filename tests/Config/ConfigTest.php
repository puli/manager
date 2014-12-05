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

use Puli\RepositoryManager\Config\Config;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigTest extends \PHPUnit_Framework_TestCase
{
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
        $default = new Config();
        $default->set(Config::PULI_DIR, 'fallback');
        $config = new Config($default);
        $config->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
    }

    public function testGetReturnsFallbackIfSet()
    {
        $default = new Config();
        $default->set(Config::PULI_DIR, 'my-puli-dir');
        $config = new Config($default);

        $this->assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
    }

    public function testGetDoesNotReturnFallbackIfDisabled()
    {
        $default = new Config();
        $default->set(Config::PULI_DIR, 'my-puli-dir');
        $config = new Config($default);

        $this->assertNull($config->get(Config::PULI_DIR, false));
    }

    public function testGetReplacesPlaceholder()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'my-puli-dir');
        $config->set(Config::READ_REPO, '{$puli-dir}/resource-repository.php');

        $this->assertSame('my-puli-dir/resource-repository.php', $config->get(Config::READ_REPO));
    }

    public function testGetReplacesPlaceholderDefinedInDefaultConfig()
    {
        $default = new Config();
        $default->set(Config::READ_REPO, '{$puli-dir}/resource-repository.php');
        $config = new Config($default);
        $config->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir/resource-repository.php', $config->get(Config::READ_REPO));
    }

    public function testGetReplacesPlaceholderSetInDefaultConfig()
    {
        $default = new Config();
        $default->set(Config::PULI_DIR, 'my-puli-dir');
        $config = new Config($default);
        $config->set(Config::READ_REPO, '{$puli-dir}/resource-repository.php');

        $this->assertSame('my-puli-dir/resource-repository.php', $config->get(Config::READ_REPO));
    }

    public function testGetDoesNotUseDefaultPlaceholderIfFallbackDisabled()
    {
        $default = new Config();
        $default->set(Config::PULI_DIR, 'my-puli-dir');
        $config = new Config($default);
        $config->set(Config::READ_REPO, '{$puli-dir}/resource-repository.php');

        $this->assertSame('/resource-repository.php', $config->get(Config::READ_REPO, false));
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

    public function testGetRawReturnsNullIfNotSet()
    {
        $config = new Config();

        $this->assertNull($config->getRaw(Config::PULI_DIR));
    }

    public function testGetRawReturnsFallbackIfSet()
    {
        $default = new Config();
        $default->set(Config::PULI_DIR, 'my-puli-dir');
        $config = new Config($default);

        $this->assertSame('my-puli-dir', $config->getRaw(Config::PULI_DIR));
    }

    public function testGetRawDoesNotReturnFallbackIfDisabled()
    {
        $default = new Config();
        $default->set(Config::PULI_DIR, 'my-puli-dir');
        $config = new Config($default);

        $this->assertNull($config->getRaw(Config::PULI_DIR, false));
    }

    public function testGetRawDoesNotReplacePlaceholder()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'my-puli-dir');
        $config->set(Config::READ_REPO, '{$puli-dir}/resource-repository.php');

        $this->assertSame('{$puli-dir}/resource-repository.php', $config->getRaw(Config::READ_REPO));
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
     * @dataProvider getNonEmptyStringKeys
     * @expectedException \Puli\RepositoryManager\InvalidConfigException
     */
    public function testSetFailsIfValueIsNotString($key)
    {
        $config = new Config();
        $config->set($key, 12345);
    }

    /**
     * @dataProvider getNonEmptyStringKeys
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
        $config->set(Config::DUMP_DIR, 'repo');
        $config->merge(array(
            Config::DUMP_DIR => 'other-repo',
            Config::READ_REPO => 'repo-file.php',
        ));

        $this->assertSame('puli-dir', $config->get(Config::PULI_DIR));
        $this->assertSame('other-repo', $config->get(Config::DUMP_DIR));
        $this->assertSame('repo-file.php', $config->get(Config::READ_REPO));
    }

    public function testRemove()
    {
        $config = new Config();
        $config->set(Config::READ_REPO, 'resource-repository.php');
        $config->set(Config::DUMP_DIR, 'repo');
        $config->remove(Config::DUMP_DIR);

        $this->assertSame('resource-repository.php', $config->get(Config::READ_REPO));
        $this->assertNull($config->get(Config::DUMP_DIR));
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
        $default = new Config();
        $default->set(Config::DUMP_DIR, 'fallback');
        $config = new Config($default);
        $config->set(Config::READ_REPO, 'resource-repository.php');
        $config->set(Config::DUMP_DIR, 'repo');
        $config->remove(Config::DUMP_DIR);

        $this->assertSame('resource-repository.php', $config->get(Config::READ_REPO));
        $this->assertSame('fallback', $config->get(Config::DUMP_DIR));
    }

    public function testToArray()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'my-puli-dir');
        $config->set(Config::READ_REPO, '{$puli-dir}/resource-repository.php');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::READ_REPO => 'my-puli-dir/resource-repository.php',
        ), $config->toArray());
    }

    public function testToArrayWithFallback()
    {
        $default = new Config();
        $default->set(Config::READ_REPO, '{$puli-dir}/resource-repository.php');
        $config = new Config($default);
        $config->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame(array(
            Config::READ_REPO => 'my-puli-dir/resource-repository.php',
            Config::PULI_DIR => 'my-puli-dir',
        ), $config->toArray());
    }

    public function testToArrayWithoutFallback()
    {
        $default = new Config();
        $default->set(Config::READ_REPO, '{$puli-dir}/resource-repository.php');
        $config = new Config($default);
        $config->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
        ), $config->toArray(false));
    }

    public function testToArrayWithoutFallbackDoesNotUsePlaceholdersFromDefaultConfig()
    {
        $default = new Config();
        $default->set(Config::PULI_DIR, 'my-puli-dir');
        $config = new Config($default);
        $config->set(Config::READ_REPO, '{$puli-dir}/resource-repository.php');

        $this->assertSame(array(
            Config::READ_REPO => '/resource-repository.php',
        ), $config->toArray(false));
    }

    public function testToRawArray()
    {
        $config = new Config();
        $config->set(Config::PULI_DIR, 'my-puli-dir');
        $config->set(Config::READ_REPO, '{$puli-dir}/resource-repository.php');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::READ_REPO => '{$puli-dir}/resource-repository.php',
        ), $config->toRawArray());
    }

    public function testToRawArrayWithFallback()
    {
        $default = new Config();
        $default->set(Config::READ_REPO, '{$puli-dir}/resource-repository.php');
        $config = new Config($default);
        $config->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame(array(
            Config::READ_REPO => '{$puli-dir}/resource-repository.php',
            Config::PULI_DIR => 'my-puli-dir',
        ), $config->toRawArray());
    }

    public function testToRawArrayWithoutFallback()
    {
        $default = new Config();
        $default->set(Config::READ_REPO, '{$puli-dir}/resource-repository.php');
        $config = new Config($default);
        $config->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
        ), $config->toRawArray(false));
    }

    public function getNotNullKeys()
    {
        return array(
            array(Config::PULI_DIR),
            array(Config::DUMP_DIR),
            array(Config::WRITE_REPO),
            array(Config::READ_REPO),
        );
    }

    public function getNonEmptyStringKeys()
    {
        return array(
            array(Config::PULI_DIR),
            array(Config::DUMP_DIR),
            array(Config::WRITE_REPO),
            array(Config::READ_REPO),
        );
    }
}
