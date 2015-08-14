<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Filesystem;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Filesystem\FilesystemStorage;
use Puli\Repository\Tests\TestUtil;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FilesystemStorageTest extends PHPUnit_Framework_TestCase
{
    const CONTENTS = "FOO\nbar\n";

    private $tempDir;

    private $path;

    /**
     * @var FilesystemStorage
     */
    private $storage;

    protected function setUp()
    {
        $this->tempDir = TestUtil::makeTempDir('puli-manager', __CLASS__);
        $this->storage = new FilesystemStorage();
        $this->path = $this->tempDir.'/test-file';

        file_put_contents($this->path, self::CONTENTS);
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testRead()
    {
        $this->assertSame(self::CONTENTS, $this->storage->read($this->path));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testReadFailsIfEmpty()
    {
        $this->storage->read('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testReadFailsIfNull()
    {
        $this->storage->read(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testReadFailsIfFalse()
    {
        $this->storage->read(false);
    }

    /**
     * @expectedException \Puli\Manager\Api\Storage\StorageException
     */
    public function testReadFailsIfDirectory()
    {
        $this->storage->read(sys_get_temp_dir());
    }

    /**
     * @expectedException \Puli\Manager\Api\Storage\StorageException
     */
    public function testReadFailsIfNotReadable()
    {
        // write, no read
        chmod($this->path, 0200);

        $this->storage->read($this->path);
    }

    public function testWrite()
    {
        $this->storage->write($this->path, self::CONTENTS);

        $this->assertSame(self::CONTENTS, file_get_contents($this->path));
    }

    public function testWriteCreatesMissingDirectories()
    {
        $this->storage->write($this->tempDir.'/sub/test-file', self::CONTENTS);

        $this->assertSame(self::CONTENTS, file_get_contents($this->tempDir.'/sub/test-file'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testWriteFailsIfEmpty()
    {
        $this->storage->write('', self::CONTENTS);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testWriteFailsIfNull()
    {
        $this->storage->write(null, self::CONTENTS);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testWriteFailsIfFalse()
    {
        $this->storage->write(false, self::CONTENTS);
    }

    /**
     * @expectedException \Puli\Manager\Api\Storage\StorageException
     */
    public function testWriteFailsIfDirectory()
    {
        $this->storage->write(sys_get_temp_dir(), self::CONTENTS);
    }

    /**
     * @expectedException \Puli\Manager\Api\Storage\StorageException
     */
    public function testWriteFailsIfNotWritable()
    {
        // read, no write
        chmod($this->path, 0400);

        $this->storage->write($this->path, self::CONTENTS);
    }

    public function testExists()
    {
        $this->assertTrue($this->storage->exists($this->path));

        unlink($this->path);

        $this->assertFalse($this->storage->exists($this->path));
    }
}
