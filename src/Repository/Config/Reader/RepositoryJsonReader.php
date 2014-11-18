<?php

/*
 * This file is part of the Puli Packages package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Packages\Repository\Config\Reader;

use Puli\Json\InvalidJsonException;
use Puli\Json\JsonDecoder;
use Puli\Packages\FileNotFoundException;
use Puli\Packages\InvalidConfigException;
use Puli\Packages\Repository\Config\PackageDefinition;
use Puli\Packages\Repository\Config\RepositoryConfig;

/**
 * Reads package configuration from a JSON file.
 *
 * The data in the JSON file is validated against the schema
 * `res/schema/config-schema.json`.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryJsonReader implements RepositoryConfigReaderInterface
{
    /**
     * Reads repository configuration from a JSON file.
     *
     * @param string $path The data source.
     *
     * @return RepositoryConfig The repository configuration.
     *
     * @throws FileNotFoundException If the JSON file was not found.
     * @throws InvalidConfigException If the JSON file is invalid.
     */
    public function readRepositoryConfig($path)
    {
        $config = new RepositoryConfig();

        $array = $this->decodeFile($path);

        foreach ($array as $packageData) {
            $packageDefinition = new PackageDefinition($packageData->installPath);
            $packageDefinition->setNew(isset($packageData->new) && $packageData->new);

            $config->addPackageDefinition($packageDefinition);
        }

        return $config;

    }

    private function decodeFile($path)
    {
        $decoder = new JsonDecoder();
        $schema = realpath(__DIR__.'/../../../../res/schema/repository-schema.json');

        if (!file_exists($path)) {
            throw new FileNotFoundException(sprintf(
                'The file "%s" does not exist.',
                $path
            ));
        }

        try {
            return $decoder->decodeFile($path, $schema);
        } catch (InvalidJsonException $e) {
            throw new InvalidConfigException(sprintf(
                "The configuration in \"%s\" is invalid:\n%s",
                $path,
                $e->getErrorsAsString()
            ), 0, $e);
        }
    }
}
