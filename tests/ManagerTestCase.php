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

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\Discovery\Api\EditableDiscovery;
use Puli\Repository\Api\EditableRepository;
use Puli\RepositoryManager\Api\Config\Config;
use Puli\RepositoryManager\Api\Config\ConfigFile;
use Puli\RepositoryManager\Api\Package\RootPackageFile;
use Puli\RepositoryManager\Config\DefaultConfig;
use Puli\RepositoryManager\Tests\Package\Fixtures\TestProjectEnvironment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class ManagerTestCase extends PHPUnit_Framework_TestCase
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
     * @var Config
     */
    protected $baseConfig;

    /**
     * @var ConfigFile
     */
    protected $configFile;

    /**
     * @var RootPackageFile
     */
    protected $rootPackageFile;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|EditableRepository
     */
    protected $repo;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|EditableDiscovery
     */
    protected $discovery;

    /**
     * @var TestProjectEnvironment
     */
    protected $environment;

    protected function initEnvironment($homeDir, $rootDir)
    {
        if (!$this->baseConfig) {
            $this->baseConfig = new DefaultConfig();
        }

        $this->homeDir = $homeDir;
        $this->rootDir = $rootDir;
        $this->configFile = new ConfigFile(null, $this->baseConfig);
        $this->rootPackageFile = new RootPackageFile('vendor/root', null, $this->baseConfig);
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->repo = $this->getMock('Puli\Repository\Api\EditableRepository');
        $this->discovery = $this->getMock('Puli\Discovery\Api\EditableDiscovery');

        $this->environment = new TestProjectEnvironment(
            $this->homeDir,
            $this->rootDir,
            $this->configFile,
            $this->rootPackageFile,
            $this->dispatcher,
            $this->repo,
            $this->discovery
        );
    }
}
