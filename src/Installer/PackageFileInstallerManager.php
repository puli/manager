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

use Exception;
use Puli\Manager\Api\Installer\InstallerDescriptor;
use Puli\Manager\Api\Installer\InstallerManager;
use Puli\Manager\Api\Installer\InstallerParameter;
use Puli\Manager\Api\Installer\NoSuchInstallerException;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFileManager;
use RuntimeException;
use stdClass;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;
use Webmozart\Json\JsonValidator;
use Webmozart\Json\ValidationFailedException;

/**
 * An installer manager that stores the installers in the package file.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileInstallerManager implements InstallerManager
{
    /**
     * The extra key that stores the installer data.
     */
    const INSTALLERS_KEY = 'installers';

    /**
     * @var array
     */
    private static $builtinInstallers = array(
        'copy' => array(
            'class' => 'Puli\Manager\Installer\CopyInstaller',
            'description' => 'Copies assets to a target directory',
        ),
        'symlink' => array(
            'class' => 'Puli\Manager\Installer\SymlinkInstaller',
            'description' => 'Creates asset symlinks in a target directory',
            'parameters' => array(
                'relative' => array(
                    'default' => true,
                    'description' => 'Whether to create relative or absolute links',
                ),
            ),
        ),
    );

    /**
     * @var RootPackageFileManager
     */
    private $rootPackageFileManager;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var RootPackage
     */
    private $rootPackage;

    /**
     * @var InstallerDescriptor[]
     */
    private $installerDescriptors;

    /**
     * @var InstallerDescriptor[]
     */
    private $rootInstallerDescriptors;

    public function __construct(RootPackageFileManager $rootPackageFileManager, PackageCollection $packages)
    {
        $this->rootPackageFileManager = $rootPackageFileManager;
        $this->packages = $packages;
        $this->rootPackage = $packages->getRootPackage();
    }

    /**
     * {@inheritdoc}
     */
    public function addRootInstallerDescriptor(InstallerDescriptor $descriptor)
    {
        $this->assertInstallersLoaded();

        $name = $descriptor->getName();

        $previouslySetInRoot = isset($this->rootInstallerDescriptors[$name]);
        $previousInstaller = $previouslySetInRoot ? $this->rootInstallerDescriptors[$name] : null;

        if (isset($this->installerDescriptors[$name]) && !$previouslySetInRoot) {
            throw new RuntimeException(sprintf(
                'An installer with the name "%s" exists already.',
                $name
            ));
        }

        try {
            $this->installerDescriptors[$name] = $descriptor;
            $this->rootInstallerDescriptors[$name] = $descriptor;

            $this->persistInstallersData();
        } catch (Exception $e) {
            if ($previouslySetInRoot) {
                $this->installerDescriptors[$name] = $previousInstaller;
                $this->rootInstallerDescriptors[$name] = $previousInstaller;
            } else {
                unset($this->installerDescriptors[$name]);
                unset($this->rootInstallerDescriptors[$name]);
            }

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeRootInstallerDescriptor($name)
    {
        $this->assertInstallersLoaded();

        $previouslySetInRoot = isset($this->rootInstallerDescriptors[$name]);
        $previousInstaller = $previouslySetInRoot ? $this->rootInstallerDescriptors[$name] : null;

        if (isset($this->installerDescriptors[$name]) && !$previouslySetInRoot) {
            throw new RuntimeException(sprintf(
                'Cannot remove installer "%s": Can only remove installers '.
                'configured in the root package.',
                $name
            ));
        }

        if (!$previouslySetInRoot) {
            return;
        }

        try {
            unset($this->installerDescriptors[$name]);
            unset($this->rootInstallerDescriptors[$name]);

            $this->persistInstallersData();
        } catch (Exception $e) {
            if ($previouslySetInRoot) {
                $this->installerDescriptors[$name] = $previousInstaller;
                $this->rootInstallerDescriptors[$name] = $previousInstaller;
            }

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeRootInstallerDescriptors(Expression $expr)
    {
        $this->assertInstallersLoaded();

        $previousInstallers = $this->rootInstallerDescriptors;
        $previousRootInstallers = $this->rootInstallerDescriptors;

        try {
            // Only remove root installers
            foreach ($previousRootInstallers as $installer) {
                if ($expr->evaluate($installer)) {
                    unset($this->installerDescriptors[$installer->getName()]);
                    unset($this->rootInstallerDescriptors[$installer->getName()]);
                }
            }

            $this->persistInstallersData();
        } catch (Exception $e) {
            $this->installerDescriptors = $previousInstallers;
            $this->rootInstallerDescriptors = $previousRootInstallers;

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearRootInstallerDescriptors()
    {
        $this->removeRootInstallerDescriptors(Expr::true());
    }

    /**
     * {@inheritdoc}
     */
    public function getRootInstallerDescriptor($name)
    {
        $this->assertInstallersLoaded();

        if (!isset($this->rootInstallerDescriptors[$name])) {
            throw NoSuchInstallerException::forInstallerNameAndPackageName($name, $this->rootPackage->getName());
        }

        return $this->rootInstallerDescriptors[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getRootInstallerDescriptors()
    {
        $this->assertInstallersLoaded();

        return $this->rootInstallerDescriptors;
    }

    /**
     * {@inheritdoc}
     */
    public function findRootInstallerDescriptors(Expression $expr)
    {
        $this->assertInstallersLoaded();

        $installers = array();

        foreach ($this->rootInstallerDescriptors as $installer) {
            if ($expr->evaluate($installer)) {
                $installers[] = $installer;
            }
        }

        return $installers;
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootInstallerDescriptor($name)
    {
        $this->assertInstallersLoaded();

        return isset($this->rootInstallerDescriptors[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootInstallerDescriptors(Expression $expr = null)
    {
        $this->assertInstallersLoaded();

        if (!$expr) {
            return count($this->rootInstallerDescriptors) > 0;
        }

        foreach ($this->rootInstallerDescriptors as $installer) {
            if ($expr->evaluate($installer)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getInstallerDescriptor($name)
    {
        $this->assertInstallersLoaded();

        if (!isset($this->installerDescriptors[$name])) {
            throw NoSuchInstallerException::forInstallerName($name);
        }

        return $this->installerDescriptors[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getInstallerDescriptors()
    {
        $this->assertInstallersLoaded();

        return $this->installerDescriptors;
    }

    /**
     * {@inheritdoc}
     */
    public function findInstallerDescriptors(Expression $expr)
    {
        $this->assertInstallersLoaded();

        $installers = array();

        foreach ($this->installerDescriptors as $installer) {
            if ($expr->evaluate($installer)) {
                $installers[] = $installer;
            }
        }

        return $installers;
    }

    /**
     * {@inheritdoc}
     */
    public function hasInstallerDescriptor($name)
    {
        $this->assertInstallersLoaded();

        return isset($this->installerDescriptors[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function hasInstallerDescriptors(Expression $expr = null)
    {
        $this->assertInstallersLoaded();

        if (!$expr) {
            return count($this->installerDescriptors) > 0;
        }

        foreach ($this->installerDescriptors as $installer) {
            if ($expr->evaluate($installer)) {
                return true;
            }
        }

        return false;
    }

    private function assertInstallersLoaded()
    {
        if (null !== $this->installerDescriptors) {
            return;
        }

        $this->installerDescriptors = array();

        foreach ($this->packages as $package) {
            if ($this->rootPackage !== $package) {
                $this->loadInstallers($package);
            }
        }

        $this->loadInstallers($this->rootPackage);
    }

    private function persistInstallersData()
    {
        $data = array();

        foreach ($this->rootInstallerDescriptors as $installerName => $installer) {
            $data[$installerName] = $this->installerToData($installer);
        }

        if ($data) {
            $this->rootPackageFileManager->setExtraKey(self::INSTALLERS_KEY, (object) $data);
        } else {
            $this->rootPackageFileManager->removeExtraKey(self::INSTALLERS_KEY);
        }
    }

    private function loadInstallers(Package $package)
    {
        foreach (self::$builtinInstallers as $name => $installerData) {
            $installer = $this->dataToInstaller($name, (object) $installerData);

            $this->installerDescriptors[$name] = $installer;
        }

        $packageFile = $package->getPackageFile();

        if (!$packageFile) {
            return;
        }

        $packageName = $package->getName();
        $installersData = $packageFile->getExtraKey(self::INSTALLERS_KEY);

        if (!$installersData) {
            return;
        }

        $jsonValidator = new JsonValidator();
        $errors = $jsonValidator->validate($installersData, __DIR__.'/../../res/schema/installers-schema-1.0.json');

        if (count($errors) > 0) {
            throw new ValidationFailedException(sprintf(
                "The extra key \"%s\" of package \"%s\" is invalid:\n%s",
                self::INSTALLERS_KEY,
                $packageName,
                implode("\n", $errors)
            ));
        }

        foreach ($installersData as $name => $installerData) {
            $installer = $this->dataToInstaller($name, $installerData);

            $this->installerDescriptors[$name] = $installer;

            if ($package instanceof RootPackage) {
                $this->rootInstallerDescriptors[$name] = $installer;
            }
        }
    }

    private function dataToInstaller($installerName, stdClass $installerData)
    {
        $parameters = array();

        if (isset($installerData->parameters)) {
            $parameters = $this->dataToParameters((object) $installerData->parameters);
        }

        return new InstallerDescriptor(
            $installerName,
            $installerData->class,
            isset($installerData->description) ? $installerData->description : null,
            $parameters
        );
    }

    private function dataToParameters(stdClass $parametersData)
    {
        $parameters = array();

        foreach ($parametersData as $parameterName => $parameterData) {
            $parameters[$parameterName] = $this->dataToParameter($parameterName, (object) $parameterData);
        }

        return $parameters;
    }

    private function dataToParameter($parameterName, stdClass $parameterData)
    {
        return new InstallerParameter(
            $parameterName,
            isset($parameterData->required) && $parameterData->required
                ? InstallerParameter::REQUIRED
                : InstallerParameter::OPTIONAL,
            isset($parameterData->default) ? $parameterData->default : null,
            isset($parameterData->description) ? $parameterData->description : null
        );
    }

    /**
     * Extracting an object containing the data from an installer descriptor.
     * 
     * @param InstallerDescriptor $installer The installer descriptor.
     * 
     * @return stdClass
     */
    private function installerToData(InstallerDescriptor $installer)
    {
        $data = (object) array(
            'class' => $installer->getClassName(),
        );

        if ($installer->getDescription()) {
            $data->description = $installer->getDescription();
        }

        if ($installer->getParameters()) {
            $data->parameters = $this->parametersToData($installer->getParameters());
        }

        return $data;
    }

    /**
     * @param InstallerParameter[] $parameters
     *
     * @return array
     */
    private function parametersToData(array $parameters)
    {
        $data = array();

        foreach ($parameters as $parameter) {
            $data[$parameter->getName()] = $this->parameterToData($parameter);
        }

        return (object) $data;
    }

    private function parameterToData(InstallerParameter $parameter)
    {
        $data = new stdClass();

        if ($parameter->isRequired()) {
            $data->required = true;
        }

        if (null !== $default = $parameter->getDefaultValue()) {
            $data->default = $default;
        }

        if ($description = $parameter->getDescription()) {
            $data->description = $description;
        }

        return $data;
    }
}
