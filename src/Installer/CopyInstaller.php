<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Installer;

use Puli\Manager\Api\Installation\InstallationParams;
use Puli\Manager\Api\Installer\ResourceInstaller;
use Puli\Repository\Api\Resource\FilesystemResource;
use Puli\Repository\Api\Resource\Resource;
use Puli\Repository\FilesystemRepository;
use Webmozart\PathUtil\Path;

/**
 * Installs resources via a local filesystem copy.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CopyInstaller implements ResourceInstaller
{
    /**
     * @var bool
     */
    protected $symlinks = false;

    /**
     * {@inheritdoc}
     */
    public function validateParams(InstallationParams $params)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function installResource(Resource $resource, InstallationParams $params)
    {
        $documentRoot = Path::makeAbsolute($params->getDocumentRoot(), $params->getRootDirectory());

        if (!file_exists($documentRoot)) {
            mkdir($documentRoot, 0777, true);
        }

        $serverPath = $params->getServerPathForResource($resource);
        $parameterValues = $params->getParameterValues();
        $relative = !isset($parameterValues['relative']) || $parameterValues['relative'];
        $filesystemRepo = new FilesystemRepository($documentRoot, $this->symlinks, $relative);

        if ('/' === $serverPath) {
            foreach ($resource->listChildren() as $child) {
                $name = $child->getName();

                // If the resource is not attached, the name is empty
                if (!$name && $child instanceof FilesystemResource) {
                    $name = Path::getFilename($child->getFilesystemPath());
                }

                if ($name) {
                    $filesystemRepo->remove($serverPath.'/'.$name);
                }
            }
        } else {
            $filesystemRepo->remove($serverPath);
        }

        $filesystemRepo->add($serverPath, $resource);
    }
}
