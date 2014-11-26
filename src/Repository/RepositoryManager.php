<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Repository;

use Puli\Filesystem\PhpCacheRepository;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\Config\RootPackageConfig;
use Puli\RepositoryManager\Project\ProjectEnvironment;
use Puli\Repository\ResourceRepository;
use Puli\Resource\NoDirectoryException;
use Puli\Util\Path;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Manages the resource repository of a Puli project.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryManager
{
    /**
     * @var ProjectEnvironment
     */
    private $environment;

    /**
     * @var RootPackageConfig
     */
    private $rootPackageConfig;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * Creates a repository manager.
     *
     * @param ProjectEnvironment $environment
     * @param PackageCollection  $packages
     */
    public function __construct(ProjectEnvironment $environment, PackageCollection $packages)
    {
        $this->environment = $environment;
        $this->rootPackageConfig = $environment->getRootPackageConfig();
        $this->rootDir = $environment->getRootDirectory();
        $this->packages = $packages;
    }

    /**
     * Dumps a resource repository.
     *
     * Pass the path where the generated resource repository is placed in the
     * first argument. You can later `require` this path to retrieve the
     * repository:
     *
     * ```php
     * $packageManager->dumpRepository('/path/to/repository.php');
     *
     * $repo = require '/path/to/repository.php';
     * ```
     *
     * In the second argument, you can pass the path where the cache files for
     * the generated resource repository are placed.
     *
     * If you don't pass any paths, the default values from the root package
     * configuration are taken.
     *
     * @param string|null $repoPath  The path to the generated resource
     *                               repository or `null` to use the configured
     *                               default path.
     * @param string|null $cachePath The path to the cache directory or `null`
     *                               to use the configured default path.
     *
     * @throws NoDirectoryException If the cache path exists and is not a
     *                              directory.
     * @throws ResourceConflictException If two packages contain conflicting
     *                                   resource definitions.
     * @throws ResourceDefinitionException If a resource definition is invalid.
     */
    public function dumpRepository($repoPath = null, $cachePath = null)
    {
        $repo = new ResourceRepository();
        $builder = new RepositoryBuilder();
        $repoPath = $repoPath ?: $this->rootPackageConfig->getGeneratedResourceRepository();
        $repoPath = Path::makeAbsolute($repoPath, $this->rootDir);
        $repoDir = Path::getDirectory($repoPath);
        $cachePath = $cachePath ?: $this->rootPackageConfig->getResourceRepositoryCache();
        $cachePath = Path::makeAbsolute($cachePath, $this->rootDir);
        $relCachePath = Path::makeRelative($cachePath, Path::getDirectory($repoPath));

        $builder->loadPackages($this->packages);
        $builder->buildRepository($repo);

        if (is_dir($cachePath)) {
            $filesystem = new Filesystem();
            $filesystem->remove($cachePath);
        }

        PhpCacheRepository::dumpRepository($repo, $cachePath);

        if (!file_exists($repoDir)) {
            $filesystem = new Filesystem();
            $filesystem->mkdir($repoDir);
        }

        file_put_contents($repoPath, <<<EOF
<?php

// generated by the Puli repository manager

use Puli\Filesystem\PhpCacheRepository;

return new PhpCacheRepository(__DIR__.'/$relCachePath');

EOF
        );
    }

}
