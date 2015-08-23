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

use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingParameterDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\InvalidConfigException;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\PackageFileSerializer;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Package\UnsupportedVersionException;
use Puli\Manager\Api\Repository\PathMapping;
use Rhumsaa\Uuid\Uuid;
use stdClass;
use Webmozart\Json\DecodingFailedException;
use Webmozart\Json\JsonDecoder;
use Webmozart\Json\JsonEncoder;
use Webmozart\Json\JsonValidator;
use Webmozart\PathUtil\Path;

/**
 * Serializes and unserializes package files to/from JSON.
 *
 * The JSON is validated against the schema `res/schema/package-schema.json`.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageJsonSerializer implements PackageFileSerializer
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
    public function serializePackageFile(PackageFile $packageFile)
    {
        $jsonData = new stdClass();

        $this->packageFileToJson($packageFile, $jsonData);

        // Sort according to key order
        $jsonArray = (array) $jsonData;
        $orderedKeys = array_intersect_key(array_flip(self::$keyOrder), $jsonArray);
        $jsonData = (object) array_replace($orderedKeys, $jsonArray);

        return $this->encode($jsonData);
    }

    /**
     * {@inheritdoc}
     */
    public function serializeRootPackageFile(RootPackageFile $packageFile)
    {
        $jsonData = new stdClass();

        $this->packageFileToJson($packageFile, $jsonData);
        $this->rootPackageFileToJson($packageFile, $jsonData);

        // Sort according to key order
        $jsonArray = (array) $jsonData;
        $orderedKeys = array_intersect_key(array_flip(self::$keyOrder), $jsonArray);
        $jsonData = (object) array_replace($orderedKeys, $jsonArray);

        return $this->encode($jsonData);
    }

    /**
     * {@inheritdoc}
     */
    public function unserializePackageFile($serialized, $path = null)
    {
        $packageFile = new PackageFile(null, $path);

        $jsonData = $this->decode($serialized, $path);

        $this->jsonToPackageFile($jsonData, $packageFile);

        return $packageFile;
    }

    /**
     * {@inheritdoc}
     */
    public function unserializeRootPackageFile($serialized, $path = null, Config $baseConfig = null)
    {
        $packageFile = new RootPackageFile(null, $path, $baseConfig);

        $jsonData = $this->decode($serialized, $path);

        $this->jsonToPackageFile($jsonData, $packageFile);
        $this->jsonToRootPackageFile($jsonData, $packageFile);

        return $packageFile;
    }

    private function packageFileToJson(PackageFile $packageFile, stdClass $jsonData)
    {
        $mappings = $packageFile->getPathMappings();
        $bindings = $packageFile->getBindingDescriptors();
        $bindingTypes = $packageFile->getTypeDescriptors();
        $overrides = $packageFile->getOverriddenPackages();
        $extra = $packageFile->getExtraKeys();

        $jsonData->version = '1.0';

        if (null !== $packageFile->getPackageName()) {
            $jsonData->name = $packageFile->getPackageName();
        }

        if (count($mappings) > 0) {
            $jsonData->{'path-mappings'} = new stdClass();

            foreach ($mappings as $mapping) {
                $puliPath = $mapping->getRepositoryPath();
                $localPaths = $mapping->getPathReferences();

                $jsonData->{'path-mappings'}->$puliPath = count($localPaths) > 1 ? $localPaths : reset($localPaths);
            }
        }

        if (count($bindings) > 0) {
            uasort($bindings, array('Puli\Manager\Api\Discovery\BindingDescriptor', 'compare'));

            $jsonData->bindings = new stdClass();

            foreach ($bindings as $binding) {
                $bindingData = new stdClass();
                $bindingData->query = $binding->getQuery();

                if ('glob' !== $binding->getLanguage()) {
                    $bindingData->language = $binding->getLanguage();
                }

                $bindingData->type = $binding->getTypeName();

                // Don't include the default values of the binding type
                if ($binding->hasParameterValues(false)) {
                    $bindingData->parameters = $binding->getParameterValues(false);
                    ksort($bindingData->parameters);
                }

                $jsonData->bindings->{$binding->getUuid()->toString()} = $bindingData;
            }
        }

        if (count($bindingTypes) > 0) {
            $bindingTypesData = array();

            foreach ($bindingTypes as $bindingType) {
                $typeData = new stdClass();

                if ($bindingType->getDescription()) {
                    $typeData->description = $bindingType->getDescription();
                }

                if (count($bindingType->getParameters()) > 0) {
                    $parametersData = array();

                    foreach ($bindingType->getParameters() as $parameter) {
                        $paramData = new stdClass();

                        if ($parameter->getDescription()) {
                            $paramData->description = $parameter->getDescription();
                        }

                        if ($parameter->isRequired()) {
                            $paramData->required = true;
                        }

                        if (null !== $parameter->getDefaultValue()) {
                            $paramData->default = $parameter->getDefaultValue();
                        }

                        $parametersData[$parameter->getName()] = $paramData;
                    }

                    ksort($parametersData);

                    $typeData->parameters = (object) $parametersData;
                }

                $bindingTypesData[$bindingType->getName()] = $typeData;
            }

            ksort($bindingTypesData);

            $jsonData->{'binding-types'} = (object) $bindingTypesData;
        }

        if (count($overrides) > 0) {
            $jsonData->override = count($overrides) > 1 ? $overrides : reset($overrides);
        }

        if (count($extra) > 0) {
            $jsonData->extra = (object) $extra;
        }
    }

    private function rootPackageFileToJson(RootPackageFile $packageFile, stdClass $jsonData)
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

                if ($installInfo->isDev()) {
                    $installData->dev = $installInfo->isDev();
                }

                $packagesData[$installInfo->getPackageName()] = $installData;
            }

            ksort($packagesData);

            $jsonData->packages = (object) $packagesData;
        }
    }

    private function jsonToPackageFile(stdClass $jsonData, PackageFile $packageFile)
    {
        if (isset($jsonData->name)) {
            $packageFile->setPackageName($jsonData->name);
        }

        if (isset($jsonData->{'path-mappings'})) {
            foreach ($jsonData->{'path-mappings'} as $path => $relativePaths) {
                $packageFile->addPathMapping(new PathMapping($path, (array) $relativePaths));
            }
        }

        if (isset($jsonData->bindings)) {
            foreach ($jsonData->bindings as $uuid => $bindingData) {
                $packageFile->addBindingDescriptor(new BindingDescriptor(
                    $bindingData->query,
                    $bindingData->type,
                    isset($bindingData->parameters) ? (array) $bindingData->parameters : array(),
                    isset($bindingData->language) ? $bindingData->language : 'glob',
                    Uuid::fromString($uuid)
                ));
            }
        }

        if (isset($jsonData->{'binding-types'})) {
            foreach ((array) $jsonData->{'binding-types'} as $typeName => $data) {
                $parameters = array();

                if (isset($data->parameters)) {
                    foreach ((array) $data->parameters as $paramName => $paramData) {
                        $required = isset($paramData->required) ? $paramData->required : false;

                        $parameters[] = new BindingParameterDescriptor(
                            $paramName,
                            $required ? BindingParameterDescriptor::REQUIRED : BindingParameterDescriptor::OPTIONAL,
                            isset($paramData->default) ? $paramData->default : null,
                            isset($paramData->description) ? $paramData->description : null
                        );
                    }
                }

                $packageFile->addTypeDescriptor(new BindingTypeDescriptor(
                    $typeName,
                    isset($data->description) ? $data->description : null,
                    $parameters
                ));
            }
        }

        if (isset($jsonData->override)) {
            $packageFile->setOverriddenPackages((array) $jsonData->override);
        }

        if (isset($jsonData->extra)) {
            $packageFile->setExtraKeys((array) $jsonData->extra);
        }
    }

    private function jsonToRootPackageFile(stdClass $jsonData, RootPackageFile $packageFile)
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
                $installInfo->setDev(isset($packageData->dev) && $packageData->dev);

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

    private function encode(stdClass $jsonData)
    {
        $encoder = new JsonEncoder();
        $encoder->setPrettyPrinting(true);
        $encoder->setEscapeSlash(false);
        $encoder->setTerminateWithLineFeed(true);
        // We can't use realpath(), which doesn't work inside PHARs.
        // However, we want to display nice paths if the file is not found.
        $schema = Path::canonicalize(__DIR__.'/../../res/schema/package-schema-1.0.json');

        return $encoder->encode($jsonData, $schema);
    }

    private function decode($json, $path = null)
    {
        $decoder = new JsonDecoder();
        $validator = new JsonValidator();
        // We can't use realpath(), which doesn't work inside PHARs.
        // However, we want to display nice paths if the file is not found.
        $schema = Path::canonicalize(__DIR__.'/../../res/schema/package-schema-1.0.json');

        try {
            $jsonData = $decoder->decode($json);
        } catch (DecodingFailedException $e) {
            throw new InvalidConfigException(sprintf(
                "The configuration%s could not be decoded:\n%s",
                $path ? ' in '.$path : '',
                $e->getMessage()
            ), $e->getCode(), $e);
        }

        if (version_compare($jsonData->version, '1.0', '<')) {
            throw UnsupportedVersionException::versionTooLow($jsonData->version, '1.0', $path);
        }

        if (version_compare($jsonData->version, '1.0', '>')) {
            throw UnsupportedVersionException::versionTooHigh($jsonData->version, '1.0', $path);
        }

        $errors = $validator->validate($jsonData, $schema);

        if (count($errors) > 0) {
            throw new InvalidConfigException(sprintf(
                "The configuration%s is invalid:\n%s",
                $path ? ' in '.$path : '',
                implode("\n", $errors)
            ));
        }

        return $jsonData;
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
