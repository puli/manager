<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\PackageFile\Writer;

use Puli\RepositoryManager\IOException;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\Json\JsonEncoder;
use Webmozart\PathUtil\Path;

/**
 * Writes JSON package files.
 *
 * The data is validated against the schema `res/schema/package-schema.json`
 * before writing.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageJsonWriter implements PackageFileWriterInterface
{
    /**
     * Writes a JSON package file.
     *
     * The data is validated against the schema `res/schema/package-schema.json`
     * before writing.
     *
     * @param PackageFile $packageFile The package file to write.
     * @param string      $path        The path to the JSON file.
     *
     * @throws IOException If the path cannot be written.
     */
    public function writePackageFile(PackageFile $packageFile, $path)
    {
        $jsonData = new \stdClass();

        $this->addConfig($jsonData, $packageFile);

        if ($packageFile instanceof RootPackageFile) {
            $this->addRootConfig($jsonData, $packageFile);
        }

        $this->encodeFile($path, $jsonData);
    }

    private function addConfig(\stdClass $jsonData, PackageFile $packageFile)
    {
        $resourceMappings = $packageFile->getResourceMappings();
        $tagMappings = $packageFile->getTagMappings();
        $overrides = $packageFile->getOverriddenPackages();

        if (null !== $packageFile->getPackageName()) {
            $jsonData->name = $packageFile->getPackageName();
        }

        if (count($resourceMappings) > 0) {
            $jsonData->resources = new \stdClass();

            foreach ($resourceMappings as $mapping) {
                $puliPath = $mapping->getPuliPath();
                $localPaths = $mapping->getLocalPaths();

                $jsonData->resources->$puliPath = count($localPaths) > 1 ? $localPaths : reset($localPaths);
            }
        }

        if (count($tagMappings) > 0) {
            $jsonData->tags = new \stdClass();

            foreach ($tagMappings as $mapping) {
                $puliSelector = $mapping->getPuliSelector();
                $tags = $mapping->getTags();

                $jsonData->tags->$puliSelector = count($tags) > 1 ? $tags : reset($tags);
            }
        }

        if (count($overrides) > 0) {
            $jsonData->override = count($overrides) > 1 ? $overrides : reset($overrides);
        }
    }

    private function addRootConfig(\stdClass $jsonData, RootPackageFile $packageFile)
    {
        $packageOrder = $packageFile->getPackageOrder();

        if (count($packageOrder) > 0) {
            $jsonData->{'package-order'} = $packageOrder;
        }

        // Pass false to exclude base configuration values
        $configValues = $packageFile->getConfig()->toRawArray(false);

        if (count($configValues) > 0) {
            $jsonData->config = (object) $configValues;
        }

        if (array() !== $packageFile->getPluginClasses(false)) {
            $jsonData->plugins = $packageFile->getPluginClasses();
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
        $schema = realpath(__DIR__.'/../../../../res/schema/package-schema.json');

        if (!is_dir($dir = Path::getDirectory($path))) {
            $filesystem = new Filesystem();
            $filesystem->mkdir($dir);
        }

        $encoder->encodeFile($path, $jsonData, $schema);
    }
}
