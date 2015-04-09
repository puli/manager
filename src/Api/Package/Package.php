<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Package;

use Exception;
use Puli\Manager\Assert\Assert;
use Webmozart\Expression\Expression;

/**
 * A configured package.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Package
{
    /**
     * The name field in {@link Expression} instances.
     */
    const NAME = 'name';

    /**
     * The install path field in {@link Expression} instances.
     */
    const INSTALL_PATH = 'installPath';

    /**
     * The state field in {@link Expression} instances.
     */
    const STATE = 'state';

    /**
     * The installer field in {@link Expression} instances.
     */
    const INSTALLER = 'installer';

    /**
     * @var string
     */
    private $name;

    /**
     * @var PackageFile
     */
    private $packageFile;

    /**
     * @var string
     */
    private $installPath;

    /**
     * @var InstallInfo
     */
    private $installInfo;

    /**
     * @var int
     */
    private $state;

    /**
     * @var Exception|null
     */
    private $loadErrors;

    /**
     * Creates a new package.
     *
     * @param PackageFile|null $packageFile The package file or `null` if the
     *                                      package file could not be loaded.
     * @param string           $installPath The absolute install path.
     * @param InstallInfo      $installInfo The install info of this package.
     * @param Exception[]      $loadErrors  The errors that happened during
     *                                      loading of the package, if any.
     */
    public function __construct(PackageFile $packageFile = null, $installPath, InstallInfo $installInfo = null, array $loadErrors = array())
    {
        Assert::absoluteSystemPath($installPath);
        Assert::true($packageFile || $loadErrors, 'The load errors must be passed if the package file is null.');
        Assert::allIsInstanceOf($loadErrors, 'Exception');

        // If a package name was set during installation, that name wins over
        // the predefined name in the puli.json file (if any)
        $this->name = $installInfo && null !== $installInfo->getPackageName()
            ? $installInfo->getPackageName()
            : ($packageFile ? $packageFile->getPackageName() : null);

        if (null === $this->name) {
            $this->name = $this->getDefaultName();
        }

        // The path is stored both here and in the install info. While the
        // install info contains the path as it is stored in the install file
        // (i.e. relative or absolute), the install path of the package is
        // always an absolute path.
        $this->installPath = $installPath;
        $this->installInfo = $installInfo;
        $this->packageFile = $packageFile;
        $this->loadErrors = $loadErrors;

        if (!file_exists($installPath)) {
            $this->state = PackageState::NOT_FOUND;
        } elseif (count($loadErrors) > 0) {
            $this->state = PackageState::NOT_LOADABLE;
        } else {
            $this->state = PackageState::ENABLED;
        }
    }

    /**
     * Returns the name of the package.
     *
     * @return string The name of the package.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the absolute path at which the package is installed.
     *
     * @return string The absolute install path of the package.
     */
    public function getInstallPath()
    {
        return $this->installPath;
    }

    /**
     * Returns the package file of the package.
     *
     * @return PackageFile|null The package file or `null` if the file could not
     *                          be loaded.
     */
    public function getPackageFile()
    {
        return $this->packageFile;
    }

    /**
     * Returns the package's install info.
     *
     * @return InstallInfo The install info.
     */
    public function getInstallInfo()
    {
        return $this->installInfo;
    }

    /**
     * Returns the error that occurred during loading of the package.
     *
     * @return Exception[] The errors or an empty array if the package was
     *                     loaded successfully.
     */
    public function getLoadErrors()
    {
        return $this->loadErrors;
    }

    /**
     * Returns the state of the package.
     *
     * @return int One of the {@link PackageState} constants.
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Returns whether the package is enabled.
     *
     * @return bool Returns `true` if the state is {@link PackageState::ENABLED}.
     *
     * @see PackageState::ENABLED
     */
    public function isEnabled()
    {
        return PackageState::ENABLED === $this->state;
    }

    /**
     * Returns whether the package was not found.
     *
     * @return bool Returns `true` if the state is {@link PackageState::NOT_FOUND}.
     *
     * @see PackageState::NOT_FOUND
     */
    public function isNotFound()
    {
        return PackageState::NOT_FOUND === $this->state;
    }

    /**
     * Returns whether the package was not loadable.
     *
     * @return bool Returns `true` if the state is {@link PackageState::NOT_LOADABLE}.
     *
     * @see PackageState::NOT_LOADABLE
     */
    public function isNotLoadable()
    {
        return PackageState::NOT_LOADABLE === $this->state;
    }

    /**
     * Returns whether the package matches the given expression.
     *
     * @param Expression $expr The search criteria. You can use the fields
     *                         {@link NAME}, {@link INSTALL_PATH} and
     *                         {@link STATE} in the expression.
     *
     * @return bool Returns `true` if the package matches the expression and
     *              `false` otherwise.
     */
    public function match(Expression $expr)
    {
        return $expr->evaluate(array(
            self::NAME => $this->name,
            self::INSTALL_PATH => $this->installPath,
            self::STATE => $this->state,
            self::INSTALLER => $this->installInfo ? $this->installInfo->getInstallerName() : null,
        ));
    }

    /**
     * Returns the default name of a package.
     *
     * @return string The default name.
     */
    protected function getDefaultName()
    {
        return null;
    }
}
