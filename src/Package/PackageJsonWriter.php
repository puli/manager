<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Package;

use Puli\Manager\Api\IOException;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\PackageFileWriter;
use Puli\Manager\Api\Package\RootPackageFile;
use stdClass;
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
        'version',
        'name',
        'path-mappings',
        'bindings',
        'binding-types',
        'override',
        'override-order',
        'config',
        'plugins',
        'extra',
        'packages',
    );

    /**
     * {@inheritdoc}
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

        $this->encodeFile((object) $jsonData, $path);
    }

    private function addConfig(array &$jsonData, PackageFile $packageFile)
    {
        $mappings = $packageFile->getPathMappings();
        $bindings = $packageFile->getBindingDescriptors();
        $bindingTypes = $packageFile->getTypeDescriptors();
        $overrides = $packageFile->getOverriddenPackages();
        $extra = $packageFile->getExtraKeys();

        $jsonData['version'] = '1.0';

        if (null !== $packageFile->getPackageName()) {
            $jsonData['name'] = $packageFile->getPackageName();
        }

        if (count($mappings) > 0) {
            $jsonData['path-mappings'] = new stdClass();

            foreach ($mappings as $mapping) {
                $puliPath = $mapping->getRepositoryPath();
                $localPaths = $mapping->getPathReferences();

                $jsonData['path-mappings']->$puliPath = count($localPaths) > 1 ? $localPaths : reset($localPaths);
            }
        }

        if (count($bindings) > 0) {
            uasort($bindings, array('Puli\Manager\Api\Discovery\BindingDescriptor', 'compare'));

            $jsonData['bindings'] = new stdClass();

            foreach ($bindings as $binding) {
                $bindingData = new stdClass();
                $bindingData->query = $binding->getQuery();

                if ('glob' !== $binding->getLanguage()) {
                    $bindingData->language = $binding->getLanguage();
                }

                $bindingData->type = $binding->getTypeName();

                // Don't include the default values of the binding type
                if ($binding->hasParameterValues(false)) {
                    $bindingData->parameters = $binding->getParameterValues(false);
                    ksort($bindingData->parameters);
                }

                $jsonData['bindings']->{$binding->getUuid()->toString()} = $bindingData;
            }
        }

        if (count($bindingTypes) > 0) {
            $bindingTypesData = array();

            foreach ($bindingTypes as $bindingType) {
                $typeData = new stdClass();

                if ($bindingType->getDescription()) {
                    $typeData->description = $bindingType->getDescription();
                }

                if (count($bindingType->getParameters()) > 0) {
                    $parametersData = array();

                    foreach ($bindingType->getParameters() as $parameter) {
                        $paramData = new stdClass();

                        if ($parameter->getDescription()) {
                            $paramData->description = $parameter->getDescription();
                        }

                        if ($parameter->isRequired()) {
                            $paramData->required = true;
                        }

                        if (null !== $parameter->getDefaultValue()) {
                            $paramData->default = $parameter->getDefaultValue();
                        }

                        $parametersData[$parameter->getName()] = $paramData;
                    }

                    ksort($parametersData);

                    $typeData->parameters = (object) $parametersData;
                }

                $bindingTypesData[$bindingType->getName()] = $typeData;
            }

            ksort($bindingTypesData);

            $jsonData['binding-types'] = (object) $bindingTypesData;
        }

        if (count($overrides) > 0) {
            $jsonData['override'] = count($overrides) > 1 ? $overrides : reset($overrides);
        }

        if (count($extra) > 0) {
            $jsonData['extra'] = (object) $extra;
        }
    }

    private function addRootConfig(array &$jsonData, RootPackageFile $packageFile)
    {
        $overrideOrder = $packageFile->getOverrideOrder();
        $installInfos = $packageFile->getInstallInfos();

        // Pass false to exclude base configuration values
        $configValues = $packageFile->getConfig()->toRawArray(false);

        if (count($overrideOrder) > 0) {
            $jsonData['override-order'] = $overrideOrder;
        }

        if (count($configValues) > 0) {
            $jsonData['config'] = (object) $configValues;
        }

        if (array() !== $packageFile->getPluginClasses(false)) {
            $jsonData['plugins'] = $packageFile->getPluginClasses();

            sort($jsonData['plugins']);
        }

        if (count($installInfos) > 0) {
            $packagesData = array();

            foreach ($installInfos as $installInfo) {
                $installData = new stdClass();
                $installData->{'install-path'} = $installInfo->getInstallPath();

                if (InstallInfo::DEFAULT_INSTALLER_NAME !== $installInfo->getInstallerName()) {
                    $installData->installer = $installInfo->getInstallerName();
                }

                if ($installInfo->hasEnabledBindingUuids()) {
                    $installData->{'enabled-bindings'} = array();

                    foreach ($installInfo->getEnabledBindingUuids() as $uuid) {
                        $installData->{'enabled-bindings'}[] = $uuid->toString();
                    }

                    sort($installData->{'enabled-bindings'});
                }

                if ($installInfo->hasDisabledBindingUuids()) {
                    $installData->{'disabled-bindings'} = array();

                    foreach ($installInfo->getDisabledBindingUuids() as $uuid) {
                        $installData->{'disabled-bindings'}[] = $uuid->toString();
                    }

                    sort($installData->{'disabled-bindings'});
                }

                if ($installInfo->isDev()) {
                    $installData->dev = $installInfo->isDev();
                }

                $packagesData[$installInfo->getPackageName()] = $installData;
            }

            ksort($packagesData);

            $jsonData['packages'] = (object) $packagesData;
        }
    }

    private function encodeFile(stdClass $jsonData, $path)
    {
        if (!is_string($path) || !Path::isAbsolute($path)) {
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
        // We can't use realpath(), which doesn't work inside PHARs.
        // However, we want to display nice paths if the file is not found.
        $schema = Path::canonicalize(__DIR__.'/../../res/schema/package-schema-1.0.json');

        if (!is_dir($dir = Path::getDirectory($path))) {
            $filesystem = new Filesystem();
            $filesystem->mkdir($dir);
        }

        $encoder->encodeFile($jsonData, $path, $schema);
    }
}
