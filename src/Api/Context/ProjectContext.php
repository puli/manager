<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Context;

use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Config\ConfigFile;
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Assert\Assert;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webmozart\PathUtil\Path;

/**
 * The context of a Puli project.
 *
 * This class contains both global context information (see {@link Context}) and
 * information local to a Puli project. It provides access to the project's root
 * directory and the root puli.json of the project.
 *
 * Use {@link getConfig()} to access the project configuration.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ProjectContext extends Context
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
     * @var string
     */
    private $env;

    /**
     * Creates the context.
     *
     * @param string|null                   $homeDir         The path to the
     *                                                       home directory or
     *                                                       `null` if none
     *                                                       exists.
     * @param string                        $rootDir         The path to the
     *                                                       project's root
     *                                                       directory.
     * @param Config                        $config          The configuration.
     * @param RootPackageFile               $rootPackageFile The root package
     *                                                       file.
     * @param ConfigFile|null               $configFile      The configuration
     *                                                       file or `null` if
     *                                                       none exists.
     * @param EventDispatcherInterface|null $dispatcher      The event
     *                                                       dispatcher.
     * @param string                        $env             The environment
     *                                                       that Puli is
     *                                                       running in.
     */
    public function __construct($homeDir, $rootDir, Config $config, RootPackageFile $rootPackageFile, ConfigFile $configFile = null, EventDispatcherInterface $dispatcher = null, $env = Environment::DEV)
    {
        Assert::directory($rootDir, 'The root directory %s is not a directory.');
        Assert::oneOf($env, Environment::all(), 'The environment must be one of: %2$s. Got: %s');

        parent::__construct($homeDir, $config, $configFile, $dispatcher);

        $this->rootDir = Path::canonicalize($rootDir);
        $this->rootPackageFile = $rootPackageFile;
        $this->env = $env;
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

    /**
     * Returns the environment that Puli is running in.
     *
     * @return string One of the {@link Environment} constants.
     */
    public function getEnvironment()
    {
        return $this->env;
    }
}
