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
use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\InvalidConfigException;
use stdClass;
use Webmozart\Json\DecodingFailedException;
use Webmozart\Json\JsonDecoder;
use Webmozart\Json\JsonEncoder;
use Webmozart\Json\ValidationFailedException;
use Webmozart\PathUtil\Path;

/**
 * Serializes and unserializes to/from JSON.
 *
 * The JSON is validated against the schema `res/schema/config-schema.json`.
 *
 * @since  1.0
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
        $decoder = new JsonDecoder();
        // We can't use realpath(), which doesn't work inside PHARs.
        // However, we want to display nice paths if the file is not found.
        $schema = $decoder->decodeFile(Path::canonicalize(__DIR__.'/../../res/schema/package-schema-1.0.json'));
        $configSchema = $schema->properties->config;

        return $encoder->encode($jsonData, $configSchema);
    }

    private function decode($json, $path = null)
    {
        $decoder = new JsonDecoder();
        // We can't use realpath(), which doesn't work inside PHARs.
        // However, we want to display nice paths if the file is not found.
        $schema = $decoder->decodeFile(Path::canonicalize(__DIR__.'/../../res/schema/package-schema-1.0.json'));
        $configSchema = $schema->properties->config;

        try {
            return $decoder->decode($json, $configSchema);
        } catch (ValidationFailedException $e) {
            throw new InvalidConfigException(sprintf(
                "The configuration%s is invalid:\n%s",
                $path ? ' in '.$path : '',
                $e->getErrorsAsString()
            ), 0, $e);
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
