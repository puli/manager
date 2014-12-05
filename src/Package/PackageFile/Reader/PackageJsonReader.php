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
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\InvalidConfigException;
use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\ResourceMapping;
use Puli\RepositoryManager\Package\TagDefinition;
use Puli\RepositoryManager\Package\TagMapping;
use Webmozart\Json\DecodingFailedException;
use Webmozart\Json\JsonDecoder;
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
class PackageJsonReader implements PackageFileReaderInterface
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

        if (isset($jsonData->tags)) {
            foreach ((array) $jsonData->tags as $selector => $tags) {
                $packageFile->addTagMapping(new TagMapping($selector, (array) $tags));
            }
        }

        if (isset($jsonData->{'tag-definitions'})) {
            foreach ($jsonData->{'tag-definitions'} as $tag => $data) {
                $packageFile->addTagDefinition(new TagDefinition($tag, isset($data->description) ? $data->description : null));
            }
        }

        if (isset($jsonData->override)) {
            $packageFile->setOverriddenPackages((array) $jsonData->override);
        }
    }

    private function populateRootConfig(RootPackageFile $packageFile, \stdClass $jsonData)
    {
        if (isset($jsonData->{'package-order'})) {
            $packageFile->setPackageOrder((array) $jsonData->{'package-order'});
        }

        if (isset($jsonData->plugins)) {
            $packageFile->setPluginClasses($jsonData->plugins);
        }

        if (isset($jsonData->config)) {
            $config = $packageFile->getConfig();

            foreach ($jsonData->config as $key => $value) {
                $config->set($key, $value);
            }
        }

        if (isset($jsonData->packages)) {
            foreach ($jsonData->packages as $packageName => $packageData) {
                $installInfo = new InstallInfo($packageName, $packageData->installPath);

                if (isset($packageData->installer)) {
                    $installInfo->setInstaller($packageData->installer);
                }

                $packageFile->addInstallInfo($installInfo);
            }
        }
    }

    private function decodeFile($path)
    {
        $decoder = new JsonDecoder();
        $schema = realpath(__DIR__.'/../../../../res/schema/package-schema.json');

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
