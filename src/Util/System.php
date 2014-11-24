<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Util;

use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\InvalidConfigException;
use Puli\PackageManager\NoDirectoryException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Provides system utilities.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class System
{
    /**
     * Parses environment variables for Puli's home directory.
     *
     * This method scans the environment variables "PULI_HOME", "HOME" and
     * "APPDATA" to determine Puli's home directory:
     *
     *  * If "PULI_HOME" is found, that directory is used.
     *  * If "HOME" is found, a directory ".puli" is created inside. This
     *    variable contains the path of the user's home directory by default
     *    on Unix based systems.
     *  * If "APPDATA" is found, a directory "Puli" is created inside. This
     *    variable contains the path of the application data by default on
     *    Windows.
     *
     * @return string The path to Puli's home directory.
     *
     * @throws InvalidConfigException If no environment variable can be found to
     *                                determine the home directory.
     * @throws FileNotFoundException If the home directory is not found.
     * @throws NoDirectoryException If the home directory is not a directory.
     */
    public static function parseHomeDirectory()
    {
        if ($value = getenv('PULI_HOME')) {
            $homeDir = $value;
            $env = 'PULI_HOME';
        } elseif ($value = getenv('HOME')) {
            $homeDir = $value;
            $env = 'HOME';
        } elseif ($value = getenv('APPDATA')) {
            $homeDir = $value;
            $env = 'APPDATA';
        } else {
            throw new InvalidConfigException(sprintf(
                'Either the environment variable PULI_HOME or %s must be set for '.
                'Puli to run.',
                defined('PHP_WINDOWS_VERSION_MAJOR') ? 'APPDATA' : 'HOME'
            ));
        }

        $homeDir = strtr($homeDir, array('\\' => '/'));

        if (!file_exists($homeDir)) {
            throw new FileNotFoundException(sprintf(
                'The home path %s defined in the environment variable %s '.
                'does not exist.',
                $homeDir,
                $env
            ));
        }

        if (is_file($homeDir)) {
            throw new NoDirectoryException(sprintf(
                'The home path %s defined in the environment variable %s '.
                'points to a file. Expected a directory path.',
                $homeDir,
                $env
            ));
        }

        switch ($env) {
            case 'PULI_HOME':
                return $homeDir; // user defined
            case 'HOME':
                return $homeDir.'/.puli'; // Linux/Mac
            default:
                return $homeDir.'/Puli'; // Windows
        }
    }

    /**
     * Denies web access to a directory path.
     *
     * A .htaccess file with the contents "Deny from all" is placed in the
     * directory, unless a .htaccess file exists already.
     *
     * @param string $directory The path to a directory.
     */
    public static function denyWebAccess($directory)
    {
        if (!file_exists($directory.'/.htaccess')) {
            if (!is_dir($directory)) {
                $filesystem = new Filesystem();
                $filesystem->mkdir($directory);
            }

            @file_put_contents($directory.'/.htaccess', 'Deny from all');
        }
    }

    private function __construct() {}
}
