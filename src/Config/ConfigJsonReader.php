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
use Puli\Manager\Api\Config\ConfigFileReader;
use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\InvalidConfigException;
use Webmozart\Json\DecodingFailedException;
use Webmozart\Json\JsonDecoder;
use Webmozart\Json\ValidationFailedException;

/**
 * Reads JSON configuration files.
 *
 * The data in the JSON file is validated against the schema
 * `res/schema/config-schema.json`.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigJsonReader implements ConfigFileReader
{
    /**
     * {@inheritdoc}
     */
    public function readConfigFile($path, Config $baseConfig = null)
    {
        $configFile = new ConfigFile($path, $baseConfig);
        $config = $configFile->getConfig();

        $jsonData = $this->objectsToArrays($this->decodeFile($path));

        foreach ($jsonData as $key => $value) {
            $config->set($key, $value);
        }

        return $configFile;
    }

    private function decodeFile($path)
    {
        $decoder = new JsonDecoder();
        $schema = $decoder->decodeFile(realpath(__DIR__.'/../../res/schema/package-schema-1.0.json'));
        $configSchema = $schema->properties->config;

        if (!file_exists($path)) {
            throw FileNotFoundException::forPath($path);
        }

        try {
            return $decoder->decodeFile($path, $configSchema);
        } catch (ValidationFailedException $e) {
            throw new InvalidConfigException(sprintf(
                "The configuration in %s is invalid:\n%s",
                $path,
                $e->getErrorsAsString()
            ), 0, $e);
        } catch (DecodingFailedException $e) {
            throw new InvalidConfigException(sprintf(
                "The configuration in %s could not be decoded:\n%s",
                $path,
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
