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
use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\InvalidConfigException;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\PackageFileReader;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Package\UnsupportedVersionException;
use Puli\Manager\Api\Repository\PathMapping;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Json\DecodingFailedException;
use Webmozart\Json\JsonDecoder;
use Webmozart\Json\JsonValidator;
use Webmozart\PathUtil\Path;

/**
 * Reads JSON package files.
 *
 * The data in the JSON file is validated against the schema
 * `res/schema/package-schema.json`.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageJsonReader implements PackageFileReader
{
    /**
     * {@inheritdoc}
     */
    public function readPackageFile($path)
    {
        $packageFile = new PackageFile(null, $path);

        $jsonData = $this->decodeFile($path);

        $this->populateConfig($packageFile, $jsonData);

        return $packageFile;
    }

    /**
     * {@inheritdoc}
     */
    public function readRootPackageFile($path, Config $baseConfig = null)
    {
        $packageFile = new RootPackageFile(null, $path, $baseConfig);

        $jsonData = $this->decodeFile($path);

        $this->populateConfig($packageFile, $jsonData);
        $this->populateRootConfig($packageFile, $jsonData);

        return $packageFile;
    }

    private function populateConfig(PackageFile $packageFile, \stdClass $jsonData)
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

    private function populateRootConfig(RootPackageFile $packageFile, \stdClass $jsonData)
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
                $isDev = false;
                if (isset( $packageData->{'dev'})) {
                    $isDev = true;
                }

                $installInfo = new InstallInfo($packageName, $packageData->{'install-path'}, $isDev);

                if (isset($packageData->installer)) {
                    $installInfo->setInstallerName($packageData->installer);
                }

                if (isset($packageData->{'enabled-bindings'})) {
                    foreach ($packageData->{'enabled-bindings'} as $uuid) {
                        $installInfo->addEnabledBindingUuid(Uuid::fromString($uuid));
                    }
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

    private function decodeFile($path)
    {
        $decoder = new JsonDecoder();
        $validator = new JsonValidator();
        // We can't use realpath(), which doesn't work inside PHARs.
        // However, we want to display nice paths if the file is not found.
        $schema = Path::canonicalize(__DIR__.'/../../res/schema/package-schema-1.0.json');

        if (!file_exists($path)) {
            throw FileNotFoundException::forPath($path);
        }

        try {
            $jsonData = $decoder->decodeFile($path);
        } catch (DecodingFailedException $e) {
            throw new InvalidConfigException(sprintf(
                "The configuration in %s could not be decoded:\n%s",
                $path,
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
                "The configuration in %s is invalid:\n%s",
                $path,
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
