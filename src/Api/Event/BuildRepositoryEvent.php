<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Event;

use Puli\Manager\Api\Repository\RepositoryManager;
use Symfony\Component\EventDispatcher\Event;

/**
 * Dispatched when the resource repository is built.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BuildRepositoryEvent extends Event
{
    /**
     * @var RepositoryManager
     */
    private $repoManager;

    /**
     * Creates the event.
     *
     * @param RepositoryManager $repoManager The repository manager.
     */
    public function __construct(RepositoryManager $repoManager)
    {
        $this->repoManager = $repoManager;
    }

    /**
     * Returns the repository manager.
     *
     * @return RepositoryManager The repository manager.
     */
    public function getRepositoryManager()
    {
        return $this->repoManager;
    }
}
