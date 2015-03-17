<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Environment;

use Puli\RepositoryManager\Api\Config\Config;
use Puli\RepositoryManager\Api\Config\ConfigFile;
use Puli\RepositoryManager\Api\Package\RootPackageFile;
use Puli\RepositoryManager\Assert\Assert;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webmozart\PathUtil\Path;

/**
 * The environment of a Puli project.
 *
 * This class contains both global environment information (see
 * {@link GlobalEnvironment}) and information local to a Puli project. It
 * provides access to the project's root directory and the root puli.json
 * of the project.
 *
 * Use {@link getConfig()} to access the project configuration.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ProjectEnvironment extends GlobalEnvironment
{
    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * Creates the environment.
     *
     * @param string|null              $homeDir         The path to the home
     *                                                  directory or `null` if
     *                                                  none exists.
     * @param string                   $rootDir         The path to the project's
     *                                                  root directory.
     * @param Config                   $config          The configuration.
     * @param RootPackageFile          $rootPackageFile The root package file.
     * @param ConfigFile               $configFile      The configuration file or
     *                                                  `null` if none exists.
     * @param EventDispatcherInterface $dispatcher      The event dispatcher.
     */
    public function __construct($homeDir, $rootDir, Config $config, RootPackageFile $rootPackageFile, ConfigFile $configFile = null, EventDispatcherInterface $dispatcher = null)
    {
        Assert::directory($rootDir, 'The root directory %s is not a directory.');

        parent::__construct($homeDir, $config, $configFile, $dispatcher);

        $this->rootDir = Path::canonicalize($rootDir);
        $this->rootPackageFile = $rootPackageFile;
    }

    /**
     * Returns the path to the project's root directory.
     *
     * @return string The root directory path.
     */
    public function getRootDirectory()
    {
        return $this->rootDir;
    }

    /**
     * Returns the root package file of the project.
     *
     * @return RootPackageFile The root package file.
     */
    public function getRootPackageFile()
    {
        return $this->rootPackageFile;
    }
}
