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
class PackageJsonWriter implements PackageFileWriter
{
    /**
     * The default order of the keys in the written package file.
     *
     * @var string[]
     */
    private static $keyOrder = array(
        'name',
        'resources',
        'bindings',
        'binding-types',
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
        $bindings = $packageFile->getBindingDescriptors();
        $bindingTypes = $packageFile->getTypeDescriptors();
        $overrides = $packageFile->getOverriddenPackages();

        if (null !== $packageFile->getPackageName()) {
            $jsonData['name'] = $packageFile->getPackageName();
        }

        if (count($resourceMappings) > 0) {
            $jsonData['resources'] = new \stdClass();

            foreach ($resourceMappings as $binding) {
                $puliPath = $binding->getPuliPath();
                $localPaths = $binding->getLocalPaths();

                $jsonData['resources']->$puliPath = count($localPaths) > 1 ? $localPaths : reset($localPaths);
            }
        }

        if (count($bindings) > 0) {
            $jsonData['bindings'] = array();

            foreach ($bindings as $binding) {
                $bindingData = new \stdClass();
                $bindingData->selector = $binding->getSelector();
                $bindingData->type = $binding->getTypeName();

                if (count($binding->getParameters()) > 0) {
                    $bindingData->parameterss = $binding->getParameters();
                }

                $jsonData['bindings'][] = $bindingData;
            }
        }

        if (count($bindingTypes) > 0) {
            $jsonData['binding-types'] = new \stdClass();

            foreach ($bindingTypes as $bindingType) {
                $typeData = new \stdClass();

                if ($bindingType->getDescription()) {
                    $typeData->description = $bindingType->getDescription();
                }

                if (count($bindingType->getParameters()) > 0) {
                    $typeData->parameters = new \stdClass();

                    foreach ($bindingType->getParameters() as $parameter) {
                        $paramData = new \stdClass();

                        if ($parameter->getDescription()) {
                            $paramData->description = $parameter->getDescription();
                        }

                        if ($parameter->isRequired()) {
                            $paramData->required = true;
                        }

                        if (null !== $parameter->getDefaultValue()) {
                            $paramData->default = $parameter->getDefaultValue();
                        }

                        $typeData->parameters->{$parameter->getName()} = $paramData;
                    }
                }

                $jsonData['binding-types']->{$bindingType->getName()} = $typeData;
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
        $configValues = $packageFile->getConfig()->toRawNestedArray(false);

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
                $installData->{'install-path'} = $installInfo->getInstallPath();

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
