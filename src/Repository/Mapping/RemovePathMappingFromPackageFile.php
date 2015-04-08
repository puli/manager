<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Repository\Mapping;

use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Removes a path mapping from the root package file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RemovePathMappingFromPackageFile implements AtomicOperation
{
    /**
     * @var string
     */
    private $repositoryPath;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var PathMapping
     */
    private $previousMapping;

    public function __construct($repositoryPath, RootPackageFile $rootPackageFile)
    {
        $this->repositoryPath = $repositoryPath;
        $this->rootPackageFile = $rootPackageFile;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if ($this->rootPackageFile->hasPathMapping($this->repositoryPath)) {
            $this->previousMapping = $this->rootPackageFile->getPathMapping($this->repositoryPath);
            $this->rootPackageFile->removePathMapping($this->repositoryPath);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->previousMapping) {
            $this->rootPackageFile->addPathMapping($this->previousMapping);
        }
    }
}
