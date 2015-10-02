<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Config;

use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Config\ConfigFile;
use Puli\Manager\Api\Config\ConfigFileSerializer;
use Puli\Manager\Api\InvalidConfigException;
use stdClass;
use Webmozart\Json\DecodingFailedException;
use Webmozart\Json\JsonDecoder;
use Webmozart\Json\JsonEncoder;

/**
 * Serializes and unserializes to/from JSON.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigJsonSerializer implements ConfigFileSerializer
{
    /**
     * {@inheritdoc}
     */
    public function serializeConfigFile(ConfigFile $configFile)
    {
        $jsonData = new stdClass();

        foreach ($configFile->getConfig()->toRawArray(false) as $key => $value) {
            $jsonData->$key = $value;
        }

        return $this->encode($jsonData);
    }

    /**
     * {@inheritdoc}
     */
    public function unserializeConfigFile($serialized, $path = null, Config $baseConfig = null)
    {
        $configFile = new ConfigFile($path, $baseConfig);
        $config = $configFile->getConfig();

        $jsonData = $this->objectsToArrays($this->decode($serialized, $path));

        foreach ($jsonData as $key => $value) {
            $config->set($key, $value);
        }

        return $configFile;
    }

    private function encode($jsonData)
    {
        $encoder = new JsonEncoder();
        $encoder->setPrettyPrinting(true);
        $encoder->setEscapeSlash(false);
        $encoder->setTerminateWithLineFeed(true);

        return $encoder->encode($jsonData);
    }

    private function decode($json, $path = null)
    {
        $decoder = new JsonDecoder();

        try {
            return $decoder->decode($json);
        } catch (DecodingFailedException $e) {
            throw new InvalidConfigException(sprintf(
                "The configuration%s could not be decoded:\n%s",
                $path ? ' in '.$path : '',
                $e->getMessage()
            ), $e->getCode(), $e);
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
