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
use Puli\RepositoryManager\Package\InstallInfo;
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
     * The default order of the keys in the written package file.
     *
     * @var string[]
     */
    private static $keyOrder = array(
        'name',
        'resources',
        'tags',
        'tag-definitions',
        'override',
        'package-order',
        'config',
        'plugins'
    );

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
        $jsonData = array();

        $this->addConfig($jsonData, $packageFile);

        if ($packageFile instanceof RootPackageFile) {
            $this->addRootConfig($jsonData, $packageFile);
        }

        // Sort according to key order
        $orderedKeys = array_intersect_key(array_flip(self::$keyOrder), $jsonData);
        $jsonData = array_replace($orderedKeys, $jsonData);

        $this->encodeFile($path, (object) $jsonData);
    }

    private function addConfig(array &$jsonData, PackageFile $packageFile)
    {
        $resourceMappings = $packageFile->getResourceMappings();
        $tagMappings = $packageFile->getTagMappings();
        $tagDefinitions = $packageFile->getTagDefinitions();
        $overrides = $packageFile->getOverriddenPackages();

        if (null !== $packageFile->getPackageName()) {
            $jsonData['name'] = $packageFile->getPackageName();
        }

        if (count($resourceMappings) > 0) {
            $jsonData['resources'] = new \stdClass();

            foreach ($resourceMappings as $mapping) {
                $puliPath = $mapping->getPuliPath();
                $localPaths = $mapping->getLocalPaths();

                $jsonData['resources']->$puliPath = count($localPaths) > 1 ? $localPaths : reset($localPaths);
            }
        }

        if (count($tagMappings) > 0) {
            $jsonData['tags'] = new \stdClass();
            $tagsBySelector = array();

            foreach ($tagMappings as $mapping) {
                $puliSelector = $mapping->getPuliSelector();

                if (!isset($tagsBySelector[$puliSelector])) {
                    $tagsBySelector[$puliSelector] = array();
                }

                $tagsBySelector[$puliSelector][] = $mapping->getTag();
            }

            foreach ($tagsBySelector as $puliSelector => $tags) {
                $jsonData['tags']->$puliSelector = count($tags) > 1 ? $tags : reset($tags);
            }
        }

        if (count($tagDefinitions) > 0) {
            $jsonData['tag-definitions'] = new \stdClass();

            foreach ($tagDefinitions as $tagDefinition) {
                $definition = new \stdClass();

                if (null !== $tagDefinition->getDescription()) {
                    $definition->description = $tagDefinition->getDescription();
                }

                $jsonData['tag-definitions']->{$tagDefinition->getTag()} = $definition;
            }
        }

        if (count($overrides) > 0) {
            $jsonData['override'] = count($overrides) > 1 ? $overrides : reset($overrides);
        }
    }

    private function addRootConfig(array &$jsonData, RootPackageFile $packageFile)
    {
        $packageOrder = $packageFile->getPackageOrder();
        $installInfos = $packageFile->getInstallInfos();

        // Pass false to exclude base configuration values
        $configValues = $packageFile->getConfig()->toRawArray(false);

        if (count($packageOrder) > 0) {
            $jsonData['package-order'] = $packageOrder;
        }

        if (count($configValues) > 0) {
            $jsonData['config'] = (object) $configValues;
        }

        if (array() !== $packageFile->getPluginClasses(false)) {
            $jsonData['plugins'] = $packageFile->getPluginClasses();
        }

        if (count($installInfos) > 0) {
            $jsonData['packages'] = new \stdClass();

            foreach ($installInfos as $installInfo) {
                $installData = new \stdClass();
                $installData->installPath = $installInfo->getInstallPath();

                if (InstallInfo::DEFAULT_INSTALLER !== $installInfo->getInstaller()) {
                    $installData->installer = $installInfo->getInstaller();
                }

                $jsonData['packages']->{$installInfo->getPackageName()} = $installData;
            }
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
