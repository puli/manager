<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Installation;

use Puli\Manager\Api\Asset\AssetMapping;
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Installation\InstallationManager;
use Puli\Manager\Api\Installation\InstallationParams;
use Puli\Manager\Api\Installation\NotInstallableException;
use Puli\Manager\Api\Installer\InstallerDescriptor;
use Puli\Manager\Api\Installer\InstallerManager;
use Puli\Manager\Api\Installer\ResourceInstaller;
use Puli\Manager\Api\Server\ServerCollection;
use Puli\Repository\Api\Resource\Resource;
use Puli\Repository\Api\ResourceRepository;
use ReflectionClass;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallationManagerImpl implements InstallationManager
{
    /**
     * @var ProjectContext
     */
    private $context;

    /**
     * @var ResourceRepository
     */
    private $repo;

    /**
     * @var ServerCollection
     */
    private $servers;

    /**
     * @var InstallerManager
     */
    private $installerManager;

    /**
     * @var ResourceInstaller[]
     */
    private $installers = array();

    public function __construct(ProjectContext $context, ResourceRepository $repo, ServerCollection $servers, InstallerManager $installerManager)
    {
        $this->context = $context;
        $this->repo = $repo;
        $this->servers = $servers;
        $this->installerManager = $installerManager;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareInstallation(AssetMapping $mapping)
    {
        $glob = $mapping->getGlob();
        $serverName = $mapping->getServerName();
        $resources = $this->repo->find($glob);

        if ($resources->isEmpty()) {
            throw NotInstallableException::noResourceMatches($glob);
        }

        if (!$this->servers->contains($serverName)) {
            throw NotInstallableException::serverNotFound($serverName);
        }

        $server = $this->servers->get($serverName);
        $installerName = $server->getInstallerName();

        if (!$this->installerManager->hasInstallerDescriptor($installerName)) {
            throw NotInstallableException::installerNotFound($installerName);
        }

        $installerDescriptor = $this->installerManager->getInstallerDescriptor($installerName);
        $installer = $this->loadInstaller($installerDescriptor);
        $rootDir = $this->context->getRootDirectory();

        $params = new InstallationParams($installer, $installerDescriptor, $resources, $mapping, $server, $rootDir);

        $installer->validateParams($params);

        return $params;
    }

    /**
     * {@inheritdoc}
     */
    public function installResource(Resource $resource, InstallationParams $params)
    {
        // Validate, as we cannot guarantee that the installation parameters
        // were actually retrieved via prepareInstallation()
        $params->getInstaller()->validateParams($params);

        // Go!
        $params->getInstaller()->installResource($resource, $params);
    }

    private function loadInstaller(InstallerDescriptor $descriptor)
    {
        $installerName = $descriptor->getName();

        if (!isset($this->installers[$installerName])) {
            $installerClass = $descriptor->getClassName();

            $this->validateInstallerClass($installerClass);

            $this->installers[$installerName] = new $installerClass();
        }

        return $this->installers[$installerName];
    }

    private function validateInstallerClass($installerClass)
    {
        if (!class_exists($installerClass)) {
            throw NotInstallableException::installerClassNotFound($installerClass);
        }

        $reflClass = new ReflectionClass($installerClass);

        if ($reflClass->hasMethod('__construct') && $reflClass->getMethod('__construct')->getNumberOfRequiredParameters() > 0) {
            throw NotInstallableException::installerClassNoDefaultConstructor($installerClass);
        }

        if (!$reflClass->implementsInterface('Puli\Manager\Api\Installer\ResourceInstaller')) {
            throw NotInstallableException::installerClassInvalid($installerClass);
        }
    }
}
