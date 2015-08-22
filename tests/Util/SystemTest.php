<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Util;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Util\System;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\Glob\Test\TestUtil;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SystemTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $tempHome;

    protected function setUp()
    {
        $this->tempHome = TestUtil::makeTempDir('puli-manager', __CLASS__);
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempHome);

        // Unset env variables
        putenv('PULI_HOME');
        putenv('HOME');
        putenv('APPDATA');
    }

    public function testParseHomeDirectory()
    {
        putenv('HOME='.$this->tempHome);

        $this->assertSame($this->tempHome.'/.puli', System::parseHomeDirectory());
    }

    public function testParseHomeDirectoryBackslashes()
    {
        putenv('HOME='.strtr($this->tempHome, '/', '\\'));

        $this->assertSame($this->tempHome.'/.puli', System::parseHomeDirectory());
    }

    public function testParseOverwrittenHomeDirectory()
    {
        putenv('HOME=/path/to/home');
        putenv('PULI_HOME='.$this->tempHome);

        $this->assertSame($this->tempHome, System::parseHomeDirectory());
    }

    public function testParseOverwrittenHomeDirectoryBackslashes()
    {
        putenv('HOME=\path\to\home');
        putenv('PULI_HOME='.strtr($this->tempHome, '/', '\\'));

        $this->assertSame($this->tempHome, System::parseHomeDirectory());
    }

    public function testParseHomeDirectoryOnWindows()
    {
        putenv('APPDATA='.$this->tempHome);

        $this->assertSame($this->tempHome.'/Puli', System::parseHomeDirectory());
    }

    public function testParseHomeDirectoryOnWindowsBackslashes()
    {
        putenv('APPDATA='.strtr($this->tempHome, '/', '\\'));

        $this->assertSame($this->tempHome.'/Puli', System::parseHomeDirectory());
    }

    public function testParseOverwrittenHomeDirectoryOnWindows()
    {
        putenv('APPDATA=C:/path/to/home');
        putenv('PULI_HOME='.$this->tempHome);

        $this->assertSame($this->tempHome, System::parseHomeDirectory());
    }

    public function testParseOverwrittenHomeDirectoryOnWindowsBackslashes()
    {
        putenv('APPDATA=C:\path\to\home');
        putenv('PULI_HOME='.strtr($this->tempHome, '/', '\\'));

        $this->assertSame($this->tempHome, System::parseHomeDirectory());
    }

    public function testFailIfNoHomeDirectoryFound()
    {
        $isWin = defined('PHP_WINDOWS_VERSION_MAJOR');

        // Mention correct variable in the exception message
        $this->setExpectedException('\Puli\Manager\Api\InvalidConfigException', $isWin ? 'APPDATA' : ' HOME ');

        System::parseHomeDirectory();
    }

    /**
     * @expectedException \Puli\Manager\Api\NoDirectoryException
     * @expectedExceptionMessage PULI_HOME
     */
    public function testFailIfHomeNoDirectory()
    {
        touch($this->tempHome.'/file');

        putenv('PULI_HOME='.$this->tempHome.'/file');

        System::parseHomeDirectory();
    }

    /**
     * @expectedException \Puli\Manager\Api\NoDirectoryException
     * @expectedExceptionMessage HOME
     */
    public function testFailIfLinuxHomeNoDirectory()
    {
        touch($this->tempHome.'/file');

        putenv('HOME='.$this->tempHome.'/file');

        System::parseHomeDirectory();
    }

    /**
     * @expectedException \Puli\Manager\Api\NoDirectoryException
     * @expectedExceptionMessage APPDATA
     */
    public function testFailIfWindowsHomeNoDirectory()
    {
        touch($this->tempHome.'/file');

        putenv('APPDATA='.$this->tempHome.'/file');

        System::parseHomeDirectory();
    }

    /**
     * @expectedException \Puli\Manager\Api\FileNotFoundException
     * @expectedExceptionMessage PULI_HOME
     */
    public function testFailIfHomeDirectoryNotFound()
    {
        putenv('PULI_HOME='.__DIR__.'/foobar');

        System::parseHomeDirectory();
    }

    /**
     * @expectedException \Puli\Manager\Api\FileNotFoundException
     * @expectedExceptionMessage HOME
     */
    public function testFailIfLinuxHomeDirectoryNotFound()
    {
        putenv('HOME='.__DIR__.'/foobar');

        System::parseHomeDirectory();
    }

    /**
     * @expectedException \Puli\Manager\Api\FileNotFoundException
     * @expectedExceptionMessage APPDATA
     */
    public function testFailIfWindowsHomeDirectoryNotFound()
    {
        putenv('APPDATA='.__DIR__.'/foobar');

        System::parseHomeDirectory();
    }

    public function testDenyWebAccess()
    {
        $this->assertFileNotExists($this->tempHome.'/.htaccess');

        System::denyWebAccess($this->tempHome);

        // Directory is protected
        $this->assertFileExists($this->tempHome.'/.htaccess');
        $this->assertSame('Deny from all', file_get_contents($this->tempHome.'/.htaccess'));
    }
}
