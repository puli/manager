<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests;

use Puli\RepositoryManager\Config\ConfigFile\ConfigFile;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Tests\Package\Fixtures\TestProjectEnvironment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class ManagerTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    protected $homeDir;

    /**
     * @var string
     */
    protected $rootDir;

    /**
     * @var ConfigFile
     */
    protected $configFile;

    /**
     * @var RootPackageFile
     */
    protected $rootPackageFile;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var TestProjectEnvironment
     */
    protected $environment;

    protected function initEnvironment($homeDir, $rootDir)
    {
        $this->homeDir = $homeDir;
        $this->rootDir = $rootDir;
        $this->configFile = new ConfigFile();
        $this->rootPackageFile = new RootPackageFile('root');
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $this->environment = new TestProjectEnvironment(
            $this->homeDir,
            $this->rootDir,
            $this->configFile,
            $this->rootPackageFile,
            $this->dispatcher
        );
    }
}
