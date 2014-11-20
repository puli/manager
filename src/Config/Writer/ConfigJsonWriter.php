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
use Puli\PackageManager\IOException;
use Puli\Util\Path;
use Symfony\Component\Filesystem\Filesystem;

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
     *
     * @throws IOException If the path cannot be written.
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
        $schema = realpath(__DIR__.'/../../../res/schema/global-schema.json');

        if (!is_dir($dir = Path::getDirectory($path))) {
            $filesystem = new Filesystem();
            $filesystem->mkdir($dir);
        }

        $encoder->encodeFile($path, $jsonData, $schema);
    }
}
