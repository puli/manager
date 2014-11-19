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
use Puli\Json\JsonValidator;
use Puli\Json\ValidationFailedException;
use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Event\JsonEvent;
use Puli\PackageManager\Event\PackageEvents;
use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\InvalidConfigException;
use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\ResourceDescriptor;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\Config\TagDescriptor;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Reads package configuration from a JSON file.
 *
 * The data in the JSON file is validated against the schema
 * `res/schema/package-schema.json`.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageJsonReader implements PackageConfigReaderInterface
{
    /**
     * @var GlobalConfig
     */
    private $globalConfig;

    /**
     * @var EventDispatcherInterface|null
     */
    private $dispatcher;

    /**
     * Creates a new configuration reader.
     *
     * You can pass an event dispatcher if you want to listen to events
     * triggered by the reader.
     *
     * @param GlobalConfig                  $globalConfig The global configuration.
     * @param EventDispatcherInterface|null $dispatcher   The event dispatcher. Optional.
     */
    public function __construct(GlobalConfig $globalConfig, EventDispatcherInterface $dispatcher = null)
    {
        $this->globalConfig = $globalConfig;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Reads package configuration from a JSON file.
     *
     * The data in the JSON file is validated against the schema
     * `res/schema/package-schema.json`.
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
     * `res/schema/package-schema.json`.
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
        $config = new RootPackageConfig($this->globalConfig);

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
            $config->setPackageRepositoryConfig($jsonData->{'package-repository'});
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
        $validator = new JsonValidator();
        $schema = realpath(__DIR__.'/../../../../res/schema/package-schema.json');

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

        // Event listeners have the opportunity to make invalid loaded files
        // valid here (e.g. add the name if it's missing)
        if ($this->dispatcher && $this->dispatcher->hasListeners(PackageEvents::PACKAGE_JSON_LOADED)) {
            $event = new JsonEvent($path, $jsonData);
            $this->dispatcher->dispatch(PackageEvents::PACKAGE_JSON_LOADED, $event);
            $jsonData = $event->getJsonData();
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
}
