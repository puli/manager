<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Repository\Config\Writer;

use Puli\Json\JsonEncoder;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;
use Puli\Util\Path;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Writes package repository configuration to a JSON file.
 *
 * The data is validated against `res/schema/repository-schema.json` before
 * writing.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryJsonWriter implements RepositoryConfigWriterInterface
{
    /**
     * Writes repository configuration to a JSON file.
     *
     * @param PackageRepositoryConfig $config The configuration to write.
     * @param mixed                   $path   The path to the JSON file.
     */
    public function writeRepositoryConfig(PackageRepositoryConfig $config, $path)
    {
        $jsonData = array();

        foreach ($config->getPackageDescriptors() as $packageDescriptor) {
            $package = new \stdClass();
            $package->installPath = $packageDescriptor->getInstallPath();

            if ($packageDescriptor->isNew()) {
                $package->new = true;
            }

            $jsonData[] = $package;
        }

        $this->encodeFile($path, $jsonData);
    }

    private function encodeFile($path, $jsonData)
    {
        $encoder = new JsonEncoder();
        $encoder->setPrettyPrinting(true);
        $encoder->setEscapeSlash(false);
        $encoder->setTerminateWithLineFeed(true);
        $schema = realpath(__DIR__.'/../../../../res/schema/repository-schema.json');

        if (!is_dir($dir = Path::getDirectory($path))) {
            $filesystem = new Filesystem();
            $filesystem->mkdir($dir);
        }

        $encoder->encodeFile($path, $jsonData, $schema);
    }
}
