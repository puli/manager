<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Config\Reader;

use Puli\RepositoryManager\Config\GlobalConfig;
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\InvalidConfigException;
use Webmozart\Json\DecodingFailedException;
use Webmozart\Json\JsonDecoder;
use Webmozart\Json\ValidationFailedException;

/**
 * Reads global configuration from a JSON file.
 *
 * The data in the JSON file is validated against the schema
 * `res/schema/global-schema.json`.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigJsonReader implements GlobalConfigReaderInterface
{
    /**
     * Reads global configuration from a JSON file.
     *
     * The data in the JSON file is validated against the schema
     * `res/schema/global-schema.json`.
     *
     * @param string $path The path to the JSON file.
     *
     * @return GlobalConfig The configuration read from the JSON file.
     *
     * @throws FileNotFoundException If the JSON file was not found.
     * @throws InvalidConfigException If the JSON file is invalid.
     */
    public function readGlobalConfig($path)
    {
        $config = new GlobalConfig($path);

        $jsonData = $this->decodeFile($path);

        if (isset($jsonData->plugins)) {
            $config->setPluginClasses($jsonData->plugins);
        }

        return $config;
    }

    private function decodeFile($path)
    {
        $decoder = new JsonDecoder();
        $schema = realpath(__DIR__.'/../../../res/schema/global-schema.json');

        if (!file_exists($path)) {
            throw new FileNotFoundException(sprintf(
                'The file %s does not exist.',
                $path
            ));
        }

        try {
            return $decoder->decodeFile($path, $schema);
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
}
