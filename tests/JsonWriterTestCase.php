<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests;

use PHPUnit_Framework_TestCase;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class JsonWriterTestCase extends PHPUnit_Framework_TestCase
{
    public static function assertJsonFileEquals($expected, $actual, $message = '', $canonicalize = false, $ignoreCase = false)
    {
        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            if (false !== strpos(PHP_VERSION, 'ubuntu')) {
                self::assertEquals(
                    file_get_contents($expected),
                    // Adjust to json_encode() compiled for Ubuntu:
                    // Remove spaces between brackets of an empty object
                    preg_replace('/(?<=\{)\s+(?=\})/', '', file_get_contents($actual)),
                    $message,
                    0,
                    10,
                    $canonicalize,
                    $ignoreCase
                );

                return;
            }

            self::assertFileEquals($expected, $actual, $message, $canonicalize, $ignoreCase);

            return;
        }

        self::assertFileExists($expected, $message);
        self::assertFileExists($actual, $message);

        self::assertEquals(
            // Adjust to json_encode() < 5.4.0:
            // Remove all newlines + following spaces except for newline at EOF
            // Remove all single spaces after a colon that is preceded by an
            // object key
            str_replace('/', '\/', preg_replace('/(?<=[^"]":) |\n\s*(?!$)/', '', file_get_contents($expected))),
            file_get_contents($actual),
            $message,
            0,
            10,
            $canonicalize,
            $ignoreCase
        );
    }
}
