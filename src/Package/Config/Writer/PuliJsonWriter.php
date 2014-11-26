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
use Puli\PackageManager\IOException;
use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\Util\Path;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Writes package configuration to a JSON file.
 *
 * The data is validated against the schema `res/schema/puli-schema.json`
 * before writing.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PuliJsonWriter implements PackageConfigWriterInterface
{
    /**
     * Writes package configuration to a JSON file.
     *
     * The data is validated against the schema `res/schema/puli-schema.json`
     * before writing.
     *
     * @param PackageConfig $config The configuration to write.
     * @param string        $path   The path to the JSON file.
     *
     * @throws IOException If the path cannot be written.
     */
    public function writePackageConfig(PackageConfig $config, $path)
    {
        $jsonData = new \stdClass();
        $defaultPackageName = null;

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

        if (null !== $config->getPackageName()) {
            $jsonData->name = $config->getPackageName();
        }

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
        if (null !== $config->getInstallFile(false)) {
            $jsonData->{'package-repository'} = $config->getInstallFile();
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
        if (!Path::isAbsolute($path)) {
            throw new IOException(sprintf(
                'Cannot write "%s": Expected an absolute path.',
                $path
            ));
        }

        if (is_dir($path)) {
            throw new IOException(sprintf(
                'Cannot write %s: Is a directory.',
                $path
            ));
        }

        $encoder = new JsonEncoder();
        $encoder->setPrettyPrinting(true);
        $encoder->setEscapeSlash(false);
        $encoder->setTerminateWithLineFeed(true);
        $schema = realpath(__DIR__.'/../../../../res/schema/puli-schema.json');

        if (!is_dir($dir = Path::getDirectory($path))) {
            $filesystem = new Filesystem();
            $filesystem->mkdir($dir);
        }

        $encoder->encodeFile($path, $jsonData, $schema);
    }
}
