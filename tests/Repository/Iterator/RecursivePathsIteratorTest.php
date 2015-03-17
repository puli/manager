<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Repository\Iterator;

use ArrayIterator;
use EmptyIterator;
use PHPUnit_Framework_TestCase;
use Puli\Manager\Repository\Iterator\RecursivePathsIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RecursivePathsIteratorTest extends PHPUnit_Framework_TestCase
{
    private $tempDir;

    protected function setUp()
    {
        while (false === mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/RecursivePathsIteratorTest'.rand(10000, 99999), 0777, true)) {}

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
        $iterator = new RecursivePathsIterator(
            new ArrayIterator(array(
                $this->tempDir.'/base.css',
                $this->tempDir.'/css',
            )),
            '/app'
        );

        $this->assertSameAfterSorting(array(
            $this->tempDir.'/base.css' => '/app',
            $this->tempDir.'/css' => '/app',
        ), iterator_to_array($iterator));

        $this->assertFalse($iterator->valid());
        $this->assertNull($iterator->current());
        $this->assertNull($iterator->key());
    }

    public function testIterateRecursively()
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursivePathsIterator(
                new ArrayIterator(array(
                    $this->tempDir.'/css',
                    $this->tempDir.'/base.css'
                )),
                '/app'
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $this->assertSameAfterSorting(array(
            $this->tempDir.'/base.css' => '/app',
            $this->tempDir.'/css' => '/app',
            $this->tempDir.'/css/reset.css' => '/app/reset.css',
            $this->tempDir.'/css/style.css' => '/app/style.css',
        ), iterator_to_array($iterator));

        $this->assertFalse($iterator->valid());
        $this->assertNull($iterator->current());

        // Different results on PHP < 5.5 and PHP >= 5.5
        $this->assertEquals(null, $iterator->key());
    }

    public function testEmptyIterator()
    {
        $iterator = new RecursivePathsIterator(new EmptyIterator(), '/app');

        $this->assertSame(array(), iterator_to_array($iterator));

        $this->assertFalse($iterator->valid());
        $this->assertNull($iterator->current());
        $this->assertNull($iterator->key());
    }

    public function testRewindFailsIfFileNotFound()
    {
        $iterator = new RecursivePathsIterator(
            new ArrayIterator(array(
                $this->tempDir.'/foo',
            )),
            '/app'
        );

        try {
            $iterator->rewind();
            $this->fail('Expected a RuntimeException');
        } catch (RuntimeException $e) {
        }

        $this->assertFalse($iterator->valid());
        $this->assertNull($iterator->current());
        $this->assertNull($iterator->key());
    }

    public function testNextFailsIfFileNotFound()
    {
        $iterator = new RecursivePathsIterator(
            new ArrayIterator(array(
                $this->tempDir.'/css',
                $this->tempDir.'/foo',
            )),
            '/app'
        );

        $iterator->rewind();

        $this->assertSame($this->tempDir.'/css', $iterator->key());
        $this->assertSame('/app', $iterator->current());

        try {
            $iterator->next();
            $this->fail('Expected a RuntimeException');
        } catch (RuntimeException $e) {
        }

        $this->assertFalse($iterator->valid());
        $this->assertNull($iterator->current());
        $this->assertNull($iterator->key());
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
