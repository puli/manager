<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\InstallFile\Reader;

use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\InvalidConfigException;
use Puli\RepositoryManager\Package\InstallFile\InstallFile;
use Puli\RepositoryManager\Package\InstallInfo;
use Webmozart\Json\DecodingFailedException;
use Webmozart\Json\JsonDecoder;
use Webmozart\Json\ValidationFailedException;

/**
 * Reads an install file from a JSON file.
 *
 * The data in the JSON file is validated against the schema
 * `res/schema/install-file-schema.json`.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallFileJsonReader implements InstallFileReaderInterface
{
    /**
     * Reads an install file from a JSON file.
     *
     * @param string $path The data source.
     *
     * @return InstallFile The install file.
     *
     * @throws FileNotFoundException If the JSON file was not found.
     * @throws InvalidConfigException If the JSON file is invalid.
     */
    public function readInstallFile($path)
    {
        $installFile = new InstallFile($path);

        $array = $this->decodeFile($path);

        foreach ($array as $packageData) {
            $installInfo = new InstallInfo($packageData->installPath);

            if (isset($packageData->name)) {
                $installInfo->setPackageName($packageData->name);
            }

            if (isset($packageData->installer)) {
                $installInfo->setInstaller($packageData->installer);
            }

            $installFile->addInstallInfo($installInfo);
        }

        return $installFile;

    }

    private function decodeFile($path)
    {
        $decoder = new JsonDecoder();
        $schema = realpath(__DIR__.'/../../../../res/schema/install-file-schema.json');

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
