<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Repository\Config\Writer;

use Puli\PackageManager\Repository\Config\PackageDescriptor;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;
use Puli\PackageManager\Repository\Config\Writer\RepositoryJsonWriter;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryJsonWriterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RepositoryJsonWriter
     */
    private $writer;

    private $tempFile;

    protected function setUp()
    {
        $this->writer = new RepositoryJsonWriter();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'PackageJsonWriterTest');
    }

    protected function tearDown()
    {
        unlink($this->tempFile);
    }

    public function testWriteConfig()
    {
        $config = new PackageRepositoryConfig();
        $config->addPackageDescriptor(new PackageDescriptor('/path/to/package1', true));
        $config->addPackageDescriptor(new PackageDescriptor('/path/to/package2', false));

        $this->writer->writeRepositoryConfig($config, $this->tempFile);

        $this->assertFileExists($this->tempFile);
        $this->assertFileEquals(__DIR__.'/Fixtures/config.json', $this->tempFile);
    }
}
