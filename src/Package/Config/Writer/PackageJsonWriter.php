<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Package\Config\Writer;

use Puli\Json\JsonEncoder;
use Puli\Json\JsonValidator;
use Puli\PackageManager\Event\JsonEvent;
use Puli\PackageManager\Event\PackageEvents;
use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Writes package configuration to a JSON file.
 *
 * The data is validated against the schema `res/schema/package-schema.json`
 * before writing.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageJsonWriter implements PackageConfigWriterInterface
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
     * Writes package configuration to a JSON file.
     *
     * The data is validated against the schema `res/schema/package-schema.json`
     * before writing.
     *
     * @param PackageConfig $config The configuration to write.
     * @param string        $path   The path to the JSON file.
     */
    public function writePackageConfig(PackageConfig $config, $path)
    {
        $jsonData = new \stdClass();

        $this->addConfig($jsonData, $config);

        if ($config instanceof RootPackageConfig) {
            $this->addRootConfig($jsonData, $config);
        }

        $this->encodeFile($path, $jsonData);
    }

    private function addConfig(\stdClass $jsonData, PackageConfig $config)
    {
        $resourceDescriptors = $config->getResourceDescriptors();
        $tagDescriptors = $config->getTagDescriptors();
        $overrides = $config->getOverriddenPackages();

        $jsonData->name = $config->getPackageName();

        if (count($resourceDescriptors) > 0) {
            $jsonData->resources = new \stdClass();

            foreach ($resourceDescriptors as $descriptor) {
                $puliPath = $descriptor->getPuliPath();
                $localPaths = $descriptor->getLocalPaths();

                $jsonData->resources->$puliPath = count($localPaths) > 1 ? $localPaths : reset($localPaths);
            }
        }

        if (count($tagDescriptors) > 0) {
            $jsonData->tags = new \stdClass();

            foreach ($tagDescriptors as $descriptor) {
                $puliSelector = $descriptor->getPuliSelector();
                $tags = $descriptor->getTags();

                $jsonData->tags->$puliSelector = count($tags) > 1 ? $tags : reset($tags);
            }
        }

        if (count($overrides) > 0) {
            $jsonData->override = count($overrides) > 1 ? $overrides : reset($overrides);
        }
    }

    private function addRootConfig(\stdClass $jsonData, RootPackageConfig $config)
    {
        $packageOrder = $config->getPackageOrder();

        if (count($packageOrder) > 0) {
            $jsonData->{'package-order'} = $packageOrder;
        }

        // Pass false to disable fallback to the global configuration values
        if (null !== $config->getPackageRepositoryConfig(false)) {
            $jsonData->{'package-repository'} = $config->getPackageRepositoryConfig();
        }

        if (null !== $config->getGeneratedResourceRepository(false)) {
            $jsonData->{'resource-repository'} = $config->getGeneratedResourceRepository();
        }

        if (null !== $config->getResourceRepositoryCache(false)) {
            $jsonData->{'resource-cache'} = $config->getResourceRepositoryCache();
        }

        if (array() !== $config->getPluginClasses(false)) {
            $jsonData->{'plugins'} = $config->getPluginClasses();
        }
    }

    private function encodeFile($path, \stdClass $jsonData)
    {
        $encoder = new JsonEncoder();
        $encoder->setPrettyPrinting(true);
        $encoder->setEscapeSlash(false);
        $encoder->setTerminateWithLineFeed(true);

        $validator = new JsonValidator();
        $schema = realpath(__DIR__.'/../../../../res/schema/package-schema.json');

        // Validate before dispatching the event
        $validator->validate($jsonData, $schema);

        // Listeners may create invalid JSON files (e.g. remove the name)
        // However, they must also make the JSON data valid again upon reading,
        // otherwise the reader will fail schema validation.
        if ($this->dispatcher && $this->dispatcher->hasListeners(PackageEvents::PACKAGE_JSON_GENERATED)) {
            $event = new JsonEvent($path, $jsonData);
            $this->dispatcher->dispatch(PackageEvents::PACKAGE_JSON_GENERATED, $event);
            $jsonData = $event->getJsonData();
        }

        $encoder->encodeFile($path, $jsonData);
    }
}
