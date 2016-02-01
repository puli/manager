<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Module;

use Puli\Manager\Api\Environment;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Assert\Assert;
use Rhumsaa\Uuid\Uuid;
use stdClass;

/**
 * Converts root module files to JSON and back.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootModuleFileConverter extends ModuleFileConverter
{
    /**
     * The default order of the keys in the written module file.
     *
     * @var string[]
     */
    private static $keyOrder = array(
        '$schema',
        'name',
        'path-mappings',
        'bindings',
        'binding-types',
        'override',
        'override-order',
        'config',
        'plugins',
        'extra',
        'modules',
    );

    /**
     * {@inheritdoc}
     */
    public function toJson($moduleFile, array $options = array())
    {
        Assert::isInstanceOf($moduleFile, 'Puli\Manager\Api\Module\RootModuleFile');

        $jsonData = new stdClass();
        $jsonData->{'$schema'} = sprintf(self::SCHEMA, self::VERSION);

        $this->addModuleFileToJson($moduleFile, $jsonData);
        $this->addRootModuleFileToJson($moduleFile, $jsonData);

        // Sort according to key order
        $jsonArray = (array) $jsonData;
        $orderedKeys = array_intersect_key(array_flip(self::$keyOrder), $jsonArray);

        return (object) array_replace($orderedKeys, $jsonArray);
    }

    /**
     * {@inheritdoc}
     */
    public function fromJson($jsonData, array $options = array())
    {
        $path = isset($options['path']) ? $options['path'] : null;
        $baseConfig = isset($options['baseConfig']) ? $options['baseConfig'] : null;

        Assert::isInstanceOf($jsonData, 'stdClass');
        Assert::nullOrString($path, 'The "path" option should be null or a string. Got: %s');
        Assert::nullOrIsInstanceOf($baseConfig, 'Puli\Manager\Api\Config\Config', 'The "baseConfig" option should be null or an instance of %2$s. Got: %s');

        $moduleFile = new RootModuleFile(null, $path, $baseConfig);
        $moduleFile->setVersion($this->versioner->parseVersion($jsonData));

        $this->addJsonToModuleFile($jsonData, $moduleFile);
        $this->addJsonToRootModuleFile($jsonData, $moduleFile);

        return $moduleFile;
    }

    protected function addRootModuleFileToJson(RootModuleFile $moduleFile, stdClass $jsonData)
    {
        $moduleOrder = $moduleFile->getModuleOrder();
        $installInfos = $moduleFile->getInstallInfos();

        // Pass false to exclude base configuration values
        $configValues = $moduleFile->getConfig()->toRawArray(false);

        if (count($moduleOrder) > 0) {
            $jsonData->order = $moduleOrder;
        }

        if (count($configValues) > 0) {
            $jsonData->config = (object) $configValues;
        }

        if (array() !== $moduleFile->getPluginClasses()) {
            $jsonData->plugins = $moduleFile->getPluginClasses();

            sort($jsonData->plugins);
        }

        if (count($installInfos) > 0) {
            $modulesData = array();

            foreach ($installInfos as $installInfo) {
                $installData = new stdClass();
                $installData->{'install-path'} = $installInfo->getInstallPath();

                if (InstallInfo::DEFAULT_INSTALLER_NAME !== $installInfo->getInstallerName()) {
                    $installData->installer = $installInfo->getInstallerName();
                }

                if ($installInfo->hasDisabledBindingUuids()) {
                    $installData->{'disabled-bindings'} = array();

                    foreach ($installInfo->getDisabledBindingUuids() as $uuid) {
                        $installData->{'disabled-bindings'}[] = $uuid->toString();
                    }

                    sort($installData->{'disabled-bindings'});
                }

                if (Environment::PROD !== $installInfo->getEnvironment()) {
                    $installData->env = $installInfo->getEnvironment();
                }

                $modulesData[$installInfo->getModuleName()] = $installData;
            }

            ksort($modulesData);

            $jsonData->modules = (object) $modulesData;
        }
    }

    protected function addJsonToRootModuleFile(stdClass $jsonData, RootModuleFile $moduleFile)
    {
        if (isset($jsonData->order)) {
            $moduleFile->setModuleOrder((array) $jsonData->order);
        }

        if (isset($jsonData->plugins)) {
            $moduleFile->setPluginClasses($jsonData->plugins);
        }

        if (isset($jsonData->config)) {
            $config = $moduleFile->getConfig();

            foreach ($this->objectsToArrays($jsonData->config) as $key => $value) {
                $config->set($key, $value);
            }
        }

        if (isset($jsonData->modules)) {
            foreach ($jsonData->modules as $moduleName => $moduleData) {
                $installInfo = new InstallInfo($moduleName, $moduleData->{'install-path'});

                if (isset($moduleData->env)) {
                    $installInfo->setEnvironment($moduleData->env);
                }

                if (isset($moduleData->installer)) {
                    $installInfo->setInstallerName($moduleData->installer);
                }

                if (isset($moduleData->{'disabled-bindings'})) {
                    foreach ($moduleData->{'disabled-bindings'} as $uuid) {
                        $installInfo->addDisabledBindingUuid(Uuid::fromString($uuid));
                    }
                }

                $moduleFile->addInstallInfo($installInfo);
            }
        }
    }

    private function objectsToArrays($data)
    {
        $data = (array) $data;

        foreach ($data as $key => $value) {
            $data[$key] = is_object($value) ? $this->objectsToArrays($value) : $value;
        }

        return $data;
    }
}
