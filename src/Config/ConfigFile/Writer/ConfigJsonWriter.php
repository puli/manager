<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Config\ConfigFile\Writer;

use Puli\RepositoryManager\Config\ConfigFile\ConfigFile;
use Puli\RepositoryManager\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\Json\JsonDecoder;
use Webmozart\Json\JsonEncoder;
use Webmozart\PathUtil\Path;

/**
 * Writes JSON configuration files.
 *
 * The data is validated against the schema `res/schema/config-schema.json`
 * before writing.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigJsonWriter implements ConfigFileWriter
{
    /**
     * Writes a JSON configuration file.
     *
     * The data is validated against the schema `res/schema/config-schema.json`
     * before writing.
     *
     * @param ConfigFile $configFile The configuration file to write.
     * @param string     $path       The path to the JSON file.
     */
    public function writeConfigFile(ConfigFile $configFile, $path)
    {
        $jsonData = new \stdClass();

        foreach ($configFile->getConfig()->toRawNestedArray(false) as $key => $value) {
            $jsonData->$key = $value;
        }

        $this->encodeFile($path, $jsonData);
    }

    private function encodeFile($path, $jsonData)
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
        $decoder = new JsonDecoder();
        $schema = $decoder->decodeFile(realpath(__DIR__.'/../../../../res/schema/package-schema.json'));
        $configSchema = $schema->properties->config;

        if (!is_dir($dir = Path::getDirectory($path))) {
            $filesystem = new Filesystem();
            $filesystem->mkdir($dir);
        }

        $encoder->encodeFile($path, $jsonData, $configSchema);
    }
}
