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
 * Removes a path mapping from the root module file.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RemovePathMappingFromModuleFile implements AtomicOperation
{
    /**
     * @var string
     */
    private $repositoryPath;

    /**
     * @var RootModuleFile
     */
    private $rootModuleFile;

    /**
     * @var PathMapping
     */
    private $previousMapping;

    public function __construct($repositoryPath, RootModuleFile $rootModuleFile)
    {
        $this->repositoryPath = $repositoryPath;
        $this->rootModuleFile = $rootModuleFile;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if ($this->rootModuleFile->hasPathMapping($this->repositoryPath)) {
            $this->previousMapping = $this->rootModuleFile->getPathMapping($this->repositoryPath);
            $this->rootModuleFile->removePathMapping($this->repositoryPath);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->previousMapping) {
            $this->rootModuleFile->addPathMapping($this->previousMapping);
        }
    }
}
