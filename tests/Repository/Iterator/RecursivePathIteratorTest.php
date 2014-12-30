<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Repository\Iterator;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Repository\Iterator\RecursivePathIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RecursivePathIteratorTest extends PHPUnit_Framework_TestCase
{
    private $tempDir;

    protected function setUp()
    {
        while (false === mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/RecursivePathIteratorTest'.rand(10000, 99999), 0777, true)) {}

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures', $this->tempDir);
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testIterate()
    {
        $iterator = new RecursivePathIterator($this->tempDir.'/css', '/app');

        $this->assertSameAfterSorting(array(
            $this->tempDir.'/css/reset.css' => '/app/reset.css',
            $this->tempDir.'/css/style.css' => '/app/style.css',
        ), iterator_to_array($iterator));

        $this->assertFalse($iterator->valid());
        $this->assertNull($iterator->current());
        $this->assertNull($iterator->key());
    }

    public function testIterateRecursively()
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursivePathIterator($this->tempDir, '/app'),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $this->assertSameAfterSorting(array(
            $this->tempDir.'/base.css' => '/app/base.css',
            $this->tempDir.'/css' => '/app/css',
            $this->tempDir.'/css/reset.css' => '/app/css/reset.css',
            $this->tempDir.'/css/style.css' => '/app/css/style.css',
            $this->tempDir.'/js' => '/app/js',
            $this->tempDir.'/js/script.js' => '/app/js/script.js',
        ), iterator_to_array($iterator));

        $this->assertFalse($iterator->valid());
        $this->assertNull($iterator->current());

        // Different results on PHP < 5.5 and PHP >= 5.5
        $this->assertEquals(null, $iterator->key());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRewindFailsIfFileNotFound()
    {
        new RecursivePathIterator($this->tempDir.'/foobar', '/app');
    }

    /**
     * Compares that an array is the same as another after sorting.
     *
     * This is necessary since RecursiveDirectoryIterator is not guaranteed to
     * return sorted results on all filesystems.
     *
     * @param mixed  $expected
     * @param mixed  $actual
     * @param string $message
     */
    private function assertSameAfterSorting($expected, $actual, $message = '')
    {
        if (is_array($actual)) {
            ksort($actual);
        }

        $this->assertSame($expected, $actual, $message);
    }
}
