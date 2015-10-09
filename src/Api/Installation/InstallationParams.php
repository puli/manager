<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Installation;

use Puli\Manager\Api\Asset\AssetMapping;
use Puli\Manager\Api\Installer\InstallerDescriptor;
use Puli\Manager\Api\Installer\ResourceInstaller;
use Puli\Manager\Api\Installer\Validation\ConstraintViolation;
use Puli\Manager\Api\Installer\Validation\InstallerParameterValidator;
use Puli\Manager\Api\Server\Server;
use Puli\Repository\Api\Resource\PuliResource;
use Puli\Repository\Api\ResourceCollection;
use Webmozart\Glob\Glob;
use Webmozart\PathUtil\Path;

/**
 * Contains all the necessary information to install resources on a server.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallationParams
{
    /**
     * @var ResourceInstaller
     */
    private $installer;

    /**
     * @var InstallerDescriptor
     */
    private $installerDescriptor;

    /**
     * @var PuliResource
     */
    private $resources;

    /**
     * @var AssetMapping
     */
    private $mapping;

    /**
     * @var Server
     */
    private $server;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var array
     */
    private $parameterValues;

    /**
     * Creates the installation request.
     *
     * @param ResourceInstaller   $installer           The used resource installer.
     * @param InstallerDescriptor $installerDescriptor The descriptor of the
     *                                                 resource installer.
     * @param ResourceCollection  $resources           The resources to install.
     * @param AssetMapping        $mapping             The asset mapping.
     * @param Server              $server              The asset server.
     * @param string              $rootDir             The project's root directory.
     */
    public function __construct(ResourceInstaller $installer, InstallerDescriptor $installerDescriptor, ResourceCollection $resources, AssetMapping $mapping, Server $server, $rootDir)
    {
        $glob = $mapping->getGlob();
        $parameterValues = $server->getParameterValues();

        $this->validateParameterValues($parameterValues, $installerDescriptor);

        $this->installer = $installer;
        $this->installerDescriptor = $installerDescriptor;
        $this->resources = $resources;
        $this->mapping = $mapping;
        $this->server = $server;
        $this->rootDir = $rootDir;
        $this->basePath = Glob::isDynamic($glob) ? Glob::getBasePath($glob) : $glob;
        $this->parameterValues = array_replace(
            $installerDescriptor->getParameterValues(),
            $parameterValues
        );
    }

    /**
     * Returns the used resource installer.
     *
     * @return ResourceInstaller The installer used to install the resources in
     *                           the server's document root.
     */
    public function getInstaller()
    {
        return $this->installer;
    }

    /**
     * Returns the descriptor of the installer.
     *
     * @return InstallerDescriptor The descriptor of the installer.
     */
    public function getInstallerDescriptor()
    {
        return $this->installerDescriptor;
    }

    /**
     * Returns the installed resources.
     *
     * @return ResourceCollection The installed resources.
     */
    public function getResources()
    {
        return $this->resources;
    }

    /**
     * Returns the asset mapping.
     *
     * @return AssetMapping The asset mapping.
     */
    public function getMapping()
    {
        return $this->mapping;
    }

    /**
     * Returns the root directory of the Puli project.
     *
     * @return string The project's root directory.
     */
    public function getRootDirectory()
    {
        return $this->rootDir;
    }

    /**
     * Returns the common base path of the installed resources.
     *
     * @return string The common base path of the installed resources.
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * Returns the target server.
     *
     * @return Server The target server.
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * Returns the document root of the server.
     *
     * This can be a directory name, a URL or any other string that can be
     * interpreted by the installer.
     *
     * @return string The document root.
     */
    public function getDocumentRoot()
    {
        return $this->server->getDocumentRoot();
    }

    /**
     * Returns the path where the resources are going to be installed.
     *
     * This is a path relative to the document root of the target server.
     *
     * @return string The server path.
     */
    public function getServerPath()
    {
        return $this->mapping->getServerPath();
    }

    /**
     * Returns the path where a resource is going to be installed.
     *
     * This is a path relative to the document root of the target server.
     *
     * @param PuliResource $resource The resource.
     *
     * @return string The server path.
     */
    public function getServerPathForResource(PuliResource $resource)
    {
        $relPath = Path::makeRelative($resource->getRepositoryPath(), $this->basePath);

        return '/'.trim($this->mapping->getServerPath().'/'.$relPath, '/');
    }

    /**
     * Returns the installer parameters.
     *
     * The result is a merge of the default parameter values of the installer
     * and the parameter values set for the server.
     *
     * @return array The installer parameters.
     */
    public function getParameterValues()
    {
        return $this->parameterValues;
    }

    private function validateParameterValues(array $parameterValues, InstallerDescriptor $installerDescriptor)
    {
        $validator = new InstallerParameterValidator();
        $violations = $validator->validate($parameterValues, $installerDescriptor);

        foreach ($violations as $violation) {
            switch ($violation->getCode()) {
                case ConstraintViolation::MISSING_PARAMETER:
                    throw NotInstallableException::missingParameter(
                        $violation->getParameterName(),
                        $violation->getInstallerName()
                    );
                case ConstraintViolation::NO_SUCH_PARAMETER:
                    throw NotInstallableException::noSuchParameter(
                        $violation->getParameterName(),
                        $violation->getInstallerName()
                    );
            }
        }
    }
}
