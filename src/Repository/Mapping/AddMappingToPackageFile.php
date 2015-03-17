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
use Puli\Manager\Api\Repository\ResourceMapping;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Adds a resource mapping to the root package file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AddMappingToPackageFile implements AtomicOperation
{
    /**
     * @var ResourceMapping
     */
    private $mapping;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var ResourceMapping
     */
    private $previousMapping;

    public function __construct(ResourceMapping $mapping, RootPackageFile $rootPackageFile)
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

        if ($this->rootPackageFile->hasResourceMapping($repositoryPath)) {
            $this->previousMapping = $this->rootPackageFile->getResourceMappings($repositoryPath);
        }

        $this->rootPackageFile->addResourceMapping($this->mapping);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->previousMapping) {
            $this->rootPackageFile->addResourceMapping($this->previousMapping);
        } else {
            $this->rootPackageFile->removeResourceMapping($this->mapping->getRepositoryPath());
        }
    }
}
