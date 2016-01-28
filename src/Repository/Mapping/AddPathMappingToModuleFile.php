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

use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Adds a path mapping to the root module file.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AddPathMappingToModuleFile implements AtomicOperation
{
    /**
     * @var PathMapping
     */
    private $mapping;

    /**
     * @var RootModuleFile
     */
    private $rootModuleFile;

    /**
     * @var PathMapping
     */
    private $previousMapping;

    public function __construct(PathMapping $mapping, RootModuleFile $rootModuleFile)
    {
        $this->mapping = $mapping;
        $this->rootModuleFile = $rootModuleFile;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $repositoryPath = $this->mapping->getRepositoryPath();

        if ($this->rootModuleFile->hasPathMapping($repositoryPath)) {
            $this->previousMapping = $this->rootModuleFile->getPathMapping($repositoryPath);
        }

        $this->rootModuleFile->addPathMapping($this->mapping);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->previousMapping) {
            $this->rootModuleFile->addPathMapping($this->previousMapping);
        } else {
            $this->rootModuleFile->removePathMapping($this->mapping->getRepositoryPath());
        }
    }
}
