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

use Puli\Json\InvalidJsonException;
use Puli\Json\JsonDecoder;
use Puli\PackageManager\Event\Events;
use Puli\PackageManager\Event\JsonEvent;
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
 * `res/schema/config-schema.json`.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageJsonReader implements PackageConfigReaderInterface
{
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
     * @param EventDispatcherInterface|null $dispatcher The event dispatcher. Optional.
     */
    public function __construct(EventDispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = $dispatcher;
    }

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
            $jsonData = $decoder->decodeFile($path, $schema);
        } catch (InvalidJsonException $e) {
            throw new InvalidConfigException(sprintf(
                "The configuration in \"%s\" is invalid:\n%s",
                $path,
                $e->getErrorsAsString()
            ), 0, $e);
        }

        if ($this->dispatcher && $this->dispatcher->hasListeners(Events::PACKAGE_JSON_LOADED)) {
            $event = new JsonEvent($jsonData);
            $this->dispatcher->dispatch(Events::PACKAGE_JSON_LOADED, $event);
            $jsonData = $event->getJsonData();
        }

        return $jsonData;
    }
}
