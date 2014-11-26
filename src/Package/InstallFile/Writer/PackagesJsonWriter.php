<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\InstallFile\Writer;

use Puli\Json\JsonEncoder;
use Puli\RepositoryManager\IOException;
use Puli\RepositoryManager\Package\InstallFile\InstallFile;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

/**
 * Writes an install file to a JSON file.
 *
 * The data is validated against `res/schema/install-file-schema.json` before
 * writing.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackagesJsonWriter implements InstallFileWriterInterface
{
    /**
     * Writes an install file to a JSON file.
     *
     * @param InstallFile $installFile The install file to write.
     * @param string      $path        The path to the JSON file.
     */
    public function writeInstallFile(InstallFile $installFile, $path)
    {
        $jsonData = array();

        foreach ($installFile->getPackageDescriptors() as $packageDescriptor) {
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
        $schema = realpath(__DIR__.'/../../../../res/schema/install-file-schema.json');

        if (!is_dir($dir = Path::getDirectory($path))) {
            $filesystem = new Filesystem();
            $filesystem->mkdir($dir);
        }

        $encoder->encodeFile($path, $jsonData, $schema);
    }
}
