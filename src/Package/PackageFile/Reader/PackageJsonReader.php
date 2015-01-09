<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\PackageFile\Reader;

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Discovery\BindingParameterDescriptor;
use Puli\RepositoryManager\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\InvalidConfigException;
use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Repository\ResourceMapping;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Json\DecodingFailedException;
use Webmozart\Json\JsonDecoder;
use Webmozart\Json\JsonValidator;
use Webmozart\Json\ValidationFailedException;

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
     * Reads a JSON package file.
     *
     * The data in the JSON file is validated against the schema
     * `res/schema/package-schema.json`.
     *
     * @param string $path The path to the JSON file.
     *
     * @return PackageFile The package file.
     *
     * @throws FileNotFoundException If the JSON file was not found.
     * @throws InvalidConfigException If the JSON file is invalid.
     */
    public function readPackageFile($path)
    {
        $packageFile = new PackageFile(null, $path);

        $jsonData = $this->decodeFile($path);

        $this->populateConfig($packageFile, $jsonData);

        return $packageFile;
    }

    /**
     * Reads a JSON root package file.
     *
     * The data in the JSON file is validated against the schema
     * `res/schema/package-schema.json`.
     *
     * @param string $path       The path to the JSON file.
     * @param Config $baseConfig The configuration that the package will inherit
     *                           its configuration values from.
     *
     * @return RootPackageFile The package file.
     *
     * @throws FileNotFoundException If the JSON file was not found.
     * @throws InvalidConfigException If the JSON file is invalid.
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

        if (isset($jsonData->resources)) {
            foreach ($jsonData->resources as $path => $relativePaths) {
                $packageFile->addResourceMapping(new ResourceMapping($path, (array) $relativePaths));
            }
        }

        if (isset($jsonData->bindings)) {
            foreach ($jsonData->bindings as $uuid => $bindingData) {
                $packageFile->addBindingDescriptor(new BindingDescriptor(
                    Uuid::fromString($uuid),
                    $bindingData->query,
                    $bindingData->type,
                    isset($bindingData->parameters) ? (array) $bindingData->parameters : array(),
                    isset($bindingData->language) ? $bindingData->language : 'glob'
                ));
            }
        }

        if (isset($jsonData->{'binding-types'})) {
            foreach ((array) $jsonData->{'binding-types'} as $typeName => $data) {
                $parameters = array();

                if (isset($data->parameters)) {
                    foreach ((array) $data->parameters as $paramName => $paramData) {
                        $parameters[] = new BindingParameterDescriptor(
                            $paramName,
                            isset($paramData->required) ? $paramData->required : false,
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
                $installInfo = new InstallInfo($packageName, $packageData->{'install-path'});

                if (isset($packageData->installer)) {
                    $installInfo->setInstaller($packageData->installer);
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
        $schema = realpath(__DIR__.'/../../../../res/schema/package-schema-1.0.json');

        if (!file_exists($path)) {
            throw new FileNotFoundException(sprintf(
                'The file %s does not exist.',
                $path
            ));
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

        try {
            $validator->validate($jsonData, $schema);
        } catch (ValidationFailedException $e) {
            throw new InvalidConfigException(sprintf(
                "The configuration in %s is invalid:\n%s",
                $path,
                $e->getErrorsAsString()
            ), $e->getCode(), $e);
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
