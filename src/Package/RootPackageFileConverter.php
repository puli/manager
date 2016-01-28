<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Package;

use Puli\Discovery\Api\Type\BindingParameter;
use Puli\Discovery\Api\Type\BindingType;
use Puli\Discovery\Binding\ClassBinding;
use Puli\Discovery\Binding\ResourceBinding;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Assert\Assert;
use Rhumsaa\Uuid\Uuid;
use stdClass;
use Webmozart\Json\Conversion\JsonConverter;

/**
 * Converts root package files to JSON and back.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageFileConverter extends PackageFileConverter
{
    /**
     * The default order of the keys in the written package file.
     *
     * @var string[]
     */
    private static $keyOrder = array(
        'version',
        'name',
        'path-mappings',
        'bindings',
        'binding-types',
        'override',
        'override-order',
        'config',
        'plugins',
        'extra',
        'packages',
    );

    /**
     * {@inheritdoc}
     */
    public function toJson($packageFile, array $options = array())
    {
        Assert::isInstanceOf($packageFile, 'Puli\Manager\Api\Package\RootPackageFile');

        $jsonData = new stdClass();

        $this->addPackageFileToJson($packageFile, $jsonData);
        $this->addRootPackageFileToJson($packageFile, $jsonData);

        // Sort according to key order
        $jsonArray = (array) $jsonData;
        $orderedKeys = array_intersect_key(array_flip(self::$keyOrder), $jsonArray);

        return (object) array_replace($orderedKeys, $jsonArray);
    }

    /**
     * {@inheritdoc}
     */
    public function fromJson($jsonData, array $options = array())
    {
        $path = isset($options['path']) ? $options['path'] : null;
        $baseConfig = isset($options['baseConfig']) ? $options['baseConfig'] : null;

        Assert::isInstanceOf($jsonData, 'stdClass');
        Assert::nullOrString($path, 'The "path" option should be null or a string. Got: %s');
        Assert::nullOrIsInstanceOf($baseConfig, 'Puli\Manager\Api\Config\Config', 'The "baseConfig" option should be null or an instance of %2$s. Got: %s');

        $packageFile = new RootPackageFile(null, $path, $baseConfig);

        $this->addJsonToPackageFile($jsonData, $packageFile);
        $this->addJsonToRootPackageFile($jsonData, $packageFile);

        return $packageFile;
    }

    protected function addRootPackageFileToJson(RootPackageFile $packageFile, stdClass $jsonData)
    {
        $overrideOrder = $packageFile->getOverrideOrder();
        $installInfos = $packageFile->getInstallInfos();

        // Pass false to exclude base configuration values
        $configValues = $packageFile->getConfig()->toRawArray(false);

        if (count($overrideOrder) > 0) {
            $jsonData->{'override-order'} = $overrideOrder;
        }

        if (count($configValues) > 0) {
            $jsonData->config = (object) $configValues;
        }

        if (array() !== $packageFile->getPluginClasses()) {
            $jsonData->plugins = $packageFile->getPluginClasses();

            sort($jsonData->plugins);
        }

        if (count($installInfos) > 0) {
            $packagesData = array();

            foreach ($installInfos as $installInfo) {
                $installData = new stdClass();
                $installData->{'install-path'} = $installInfo->getInstallPath();

                if (InstallInfo::DEFAULT_INSTALLER_NAME !== $installInfo->getInstallerName()) {
                    $installData->installer = $installInfo->getInstallerName();
                }

                if ($installInfo->hasDisabledBindingUuids()) {
                    $installData->{'disabled-bindings'} = array();

                    foreach ($installInfo->getDisabledBindingUuids() as $uuid) {
                        $installData->{'disabled-bindings'}[] = $uuid->toString();
                    }

                    sort($installData->{'disabled-bindings'});
                }

                if (Environment::PROD !== $installInfo->getEnvironment()) {
                    $installData->env = $installInfo->getEnvironment();
                }

                $packagesData[$installInfo->getPackageName()] = $installData;
            }

            ksort($packagesData);

            $jsonData->packages = (object) $packagesData;
        }
    }

    protected function addJsonToRootPackageFile(stdClass $jsonData, RootPackageFile $packageFile)
    {
        if (isset($jsonData->{'override-order'})) {
            $packageFile->setOverrideOrder((array) $jsonData->{'override-order'});
        }

        if (isset($jsonData->plugins)) {
            $packageFile->setPluginClasses($jsonData->plugins);
        }

        if (isset($jsonData->config)) {
            $config = $packageFile->getConfig();

            foreach ($this->objectsToArrays($jsonData->config) as $key => $value) {
                $config->set($key, $value);
            }
        }

        if (isset($jsonData->packages)) {
            foreach ($jsonData->packages as $packageName => $packageData) {
                $installInfo = new InstallInfo($packageName, $packageData->{'install-path'});

                if (isset($packageData->env)) {
                    $installInfo->setEnvironment($packageData->env);
                }

                if (isset($packageData->installer)) {
                    $installInfo->setInstallerName($packageData->installer);
                }

                if (isset($packageData->{'disabled-bindings'})) {
                    foreach ($packageData->{'disabled-bindings'} as $uuid) {
                        $installInfo->addDisabledBindingUuid(Uuid::fromString($uuid));
                    }
                }

                $packageFile->addInstallInfo($installInfo);
            }
        }
    }

    private function objectsToArrays($data)
    {
        $data = (array) $data;

        foreach ($data as $key => $value) {
            $data[$key] = is_object($value) ? $this->objectsToArrays($value) : $value;
        }

        return $data;
    }
}
