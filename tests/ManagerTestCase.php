<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\Discovery\Api\EditableDiscovery;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Config\ConfigFile;
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Config\DefaultConfig;
use Puli\Repository\Api\EditableRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 *
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
     * @var ProjectContext
     */
    protected $context;

    protected function initContext($homeDir, $rootDir, $mockDispatcher = true)
    {
        if (!$this->baseConfig) {
            $this->baseConfig = new DefaultConfig();
        }

        $this->homeDir = $homeDir;
        $this->rootDir = $rootDir;
        $this->configFile = new ConfigFile($homeDir.'/config.json', $this->baseConfig);
        $this->rootPackageFile = new RootPackageFile('vendor/root', $rootDir.'/puli.json', $this->baseConfig);
        $this->dispatcher = $mockDispatcher
            ? $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface')
            : new EventDispatcher();
        $this->repo = $this->getMock('Puli\Repository\Api\EditableRepository');
        $this->discovery = $this->getMock('Puli\Discovery\Api\EditableDiscovery');

        $this->context = new ProjectContext(
            $this->homeDir,
            $this->rootDir,
            $this->rootPackageFile->getConfig(),
            $this->rootPackageFile,
            $this->configFile,
            $this->dispatcher
        );
    }
}
