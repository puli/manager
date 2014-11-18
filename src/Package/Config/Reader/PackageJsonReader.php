<?php

/*
 * This file is part of the Puli Packages package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Packages\Package\Config\Reader;

use Puli\Json\InvalidJsonException;
use Puli\Json\JsonDecoder;
use Puli\Packages\FileNotFoundException;
use Puli\Packages\InvalidConfigException;
use Puli\Packages\Package\Config\PackageConfig;
use Puli\Packages\Package\Config\ResourceDefinition;
use Puli\Packages\Package\Config\RootPackageConfig;
use Puli\Packages\Package\Config\TagDefinition;

/**
 * Reads package configuration from a JSON file.
 *
 * The data in the JSON file is validated against the schema
 * `res/schema/config-schema.json`.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageJsonReader implements PackageConfigReaderInterface
{
    /**
     * Reads package configuration from a JSON file.
     *
     * The data in the JSON file is validated against the schema
     * `res/schema/config-schema.json`.
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
        $config = new PackageConfig();

        $jsonData = $this->decodeFile($path);

        $this->populateConfig($config, $jsonData);

        return $config;
    }

    /**
     * Reads root package configuration from a JSON file.
     *
     * The data in the JSON file is validated against the schema
     * `res/schema/config-schema.json`.
     *
     * @param string $path The path to the JSON file.
     *
     * @return RootPackageConfig The configuration read from the JSON file.
     *
     * @throws FileNotFoundException If the JSON file was not found.
     * @throws InvalidConfigException If the JSON file is invalid.
     */
    public function readRootPackageConfig($path)
    {
        $config = new RootPackageConfig();

        $jsonData = $this->decodeFile($path);

        $this->populateConfig($config, $jsonData);
        $this->populateRootConfig($config, $jsonData);

        return $config;
    }

    private function populateConfig(PackageConfig $config, \stdClass $jsonData)
    {
        $config->setPackageName($jsonData->name);

        if (isset($jsonData->resources)) {
            foreach ($jsonData->resources as $path => $relativePaths) {
                $config->addResourceDefinition(new ResourceDefinition($path, (array) $relativePaths));
            }
        }

        if (isset($jsonData->tags)) {
            foreach ((array) $jsonData->tags as $selector => $tags) {
                $config->addTagDefinition(new TagDefinition($selector, (array) $tags));
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
    }

    private function decodeFile($path)
    {
        $decoder = new JsonDecoder();
        $schema = realpath(__DIR__.'/../../../../res/schema/package-schema.json');

        if (!file_exists($path)) {
            throw new FileNotFoundException(sprintf(
                'The file "%s" does not exist.',
                $path
            ));
        }

        try {
            return $decoder->decodeFile($path, $schema);
        } catch (InvalidJsonException $e) {
            throw new InvalidConfigException(sprintf(
                "The configuration in \"%s\" is invalid:\n%s",
                $path,
                $e->getErrorsAsString()
            ), 0, $e);
        }
    }
}
