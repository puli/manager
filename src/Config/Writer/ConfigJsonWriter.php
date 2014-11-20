<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Config\Writer;

use Puli\Json\JsonEncoder;
use Puli\PackageManager\Config\GlobalConfig;

/**
 * Writes global configuration to a JSON file.
 *
 * The data is validated against the schema `res/schema/global-schema.json`
 * before writing.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigJsonWriter implements GlobalConfigWriterInterface
{
    /**
     * Writes global configuration to a JSON file.
     *
     * The data is validated against the schema `res/schema/global-schema.json`
     * before writing.
     *
     * @param GlobalConfig $config The configuration to write.
     * @param string       $path   The path to the JSON file.
     */
    public function writeGlobalConfig(GlobalConfig $config, $path)
    {
        $jsonData = new \stdClass();

        if (count($config->getPluginClasses()) > 0) {
            $jsonData->plugins = array();

            foreach ($config->getPluginClasses() as $pluginClass) {
                $jsonData->plugins[] = $pluginClass;
            }
        }

        $this->encodeFile($path, $jsonData);
    }

    private function encodeFile($path, $jsonData)
    {
        $encoder = new JsonEncoder();
        $encoder->setPrettyPrinting(true);
        $encoder->setEscapeSlash(false);
        $encoder->setTerminateWithLineFeed(true);
        $schema = realpath(__DIR__.'/../../../res/schema/global-schema.json');

        $encoder->encodeFile($path, $jsonData, $schema);
    }
}
