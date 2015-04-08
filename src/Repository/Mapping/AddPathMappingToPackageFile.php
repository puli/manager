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
 * Adds a path mapping to the root package file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AddPathMappingToPackageFile implements AtomicOperation
{
    /**
     * @var PathMapping
     */
    private $mapping;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var PathMapping
     */
    private $previousMapping;

    public function __construct(PathMapping $mapping, RootPackageFile $rootPackageFile)
    {
        $this->mapping = $mapping;
        $this->rootPackageFile = $rootPackageFile;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $repositoryPath = $this->mapping->getRepositoryPath();

        if ($this->rootPackageFile->hasPathMapping($repositoryPath)) {
            $this->previousMapping = $this->rootPackageFile->getPathMappings($repositoryPath);
        }

        $this->rootPackageFile->addPathMapping($this->mapping);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->previousMapping) {
            $this->rootPackageFile->addPathMapping($this->previousMapping);
        } else {
            $this->rootPackageFile->removePathMapping($this->mapping->getRepositoryPath());
        }
    }
}
