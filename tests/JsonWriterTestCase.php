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

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class JsonWriterTestCase extends \PHPUnit_Framework_TestCase
{
    public static function assertJsonFileEquals($expected, $actual, $message = '', $canonicalize = false, $ignoreCase = false)
    {
        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            self::assertFileEquals($expected, $actual, $message, $canonicalize, $ignoreCase);

            return;
        }

        self::assertFileExists($expected, $message);
        self::assertFileExists($actual, $message);

        self::assertEquals(
            // Adjust to json_encode() < 5.4.0:
            // Remove all newlines + following spaces except for newline at EOF
            // Remove all single spaces between a colon ":" and one of '"', "{" or "["
            // Replace all "/" by "\/"
            str_replace('/', '\/', preg_replace('/(?<=:) (?=["{[])|\n\s*(?!$)/', '', file_get_contents($expected))),
            file_get_contents($actual),
            $message,
            0,
            10,
            $canonicalize,
            $ignoreCase
        );
    }
}
