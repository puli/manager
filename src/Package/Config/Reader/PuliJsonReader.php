<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Package\Config\Reader;

use Puli\Json\DecodingFailedException;
use Puli\Json\JsonDecoder;
use Puli\Json\ValidationFailedException;
use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\InvalidConfigException;
use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\ResourceDescriptor;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\Config\TagDescriptor;

/**
 * Reads package configuration from a JSON file.
 *
 * The data in the JSON file is validated against the schema
 * `res/schema/puli-schema.json`.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PuliJsonReader implements PackageConfigReaderInterface
{
    /**
     * Reads package configuration from a JSON file.
     *
     * The data in the JSON file is validated against the schema
     * `res/schema/puli-schema.json`.
     *
     * @param string $path The path to the JSON file.
     *
     * @return PackageConfig The configuration read from the JSON file.
     *
     * @throws FileNotFoundException If the JSON file was not found.
     * @throws InvalidConfigException If the JSON file is invalid.
     */
    public function readPackageConfig($path)
    {
        $config = new PackageConfig(null, $path);

        $jsonData = $this->decodeFile($path);

        $this->populateConfig($config, $jsonData);

        return $config;
    }

    /**
     * Reads root package configuration from a JSON file.
     *
     * The data in the JSON file is validated against the schema
     * `res/schema/puli-schema.json`.
     *
     * @param string       $path         The path to the JSON file.
     * @param GlobalConfig $globalConfig The global configuration that the root
     *                                   configuration will inherit its settings
     *                                   from.
     *
     * @return RootPackageConfig The configuration read from the JSON file.
     *
     * @throws FileNotFoundException If the JSON file was not found.
     * @throws InvalidConfigException If the JSON file is invalid.
     */
    public function readRootPackageConfig($path, GlobalConfig $globalConfig)
    {
        $config = new RootPackageConfig($globalConfig, null, $path);

        $jsonData = $this->decodeFile($path);

        $this->populateConfig($config, $jsonData);
        $this->populateRootConfig($config, $jsonData);

        return $config;
    }

    private function populateConfig(PackageConfig $config, \stdClass $jsonData)
    {
        if (isset($jsonData->name)) {
            $config->setPackageName($jsonData->name);
        }

        if (isset($jsonData->resources)) {
            foreach ($jsonData->resources as $path => $relativePaths) {
                $config->addResourceDescriptor(new ResourceDescriptor($path, (array) $relativePaths));
            }
        }

        if (isset($jsonData->tags)) {
            foreach ((array) $jsonData->tags as $selector => $tags) {
                $config->addTagDescriptor(new TagDescriptor($selector, (array) $tags));
            }
        }

        if (isset($jsonData->override)) {
            $config->setOverriddenPackages((array) $jsonData->override);
        }
    }

    private function populateRootConfig(RootPackageConfig $config, \stdClass $jsonData)
    {
        if (isset($jsonData->{'package-order'})) {
            $config->setPackageOrder((array) $jsonData->{'package-order'});
        }

        if (isset($jsonData->{'package-repository'})) {
            $config->setInstallFile($jsonData->{'package-repository'});
        }

        if (isset($jsonData->{'resource-repository'})) {
            $config->setGeneratedResourceRepository($jsonData->{'resource-repository'});
        }

        if (isset($jsonData->{'resource-cache'})) {
            $config->setResourceRepositoryCache($jsonData->{'resource-cache'});
        }

        if (isset($jsonData->{'plugins'})) {
            $config->setPluginClasses($jsonData->{'plugins'});
        }
    }

    private function decodeFile($path)
    {
        $decoder = new JsonDecoder();
        $schema = realpath(__DIR__.'/../../../../res/schema/puli-schema.json');

        if (!file_exists($path)) {
            throw new FileNotFoundException(sprintf(
                'The file %s does not exist.',
                $path
            ));
        }

        try {
            $jsonData = $decoder->decodeFile($path, $schema);
        } catch (DecodingFailedException $e) {
            throw new InvalidConfigException(sprintf(
                "The configuration in %s could not be decoded:\n%s",
                $path,
                $e->getMessage()
            ), $e->getCode(), $e);
        } catch (ValidationFailedException $e) {
            throw new InvalidConfigException(sprintf(
                "The configuration in %s is invalid:\n%s",
                $path,
                $e->getErrorsAsString()
            ), $e->getCode(), $e);
        }

        return $jsonData;
    }
}
