<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Conflict;

use Exception;
use RuntimeException;

/**
 * Thrown when two packages have conflicting resource mappings.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageConflictException extends RuntimeException
{
    public static function forPathConflict(PackageConflict $conflict, $code = 0, Exception $cause = null)
    {
        return new static(sprintf(
            "The packages \"%s\" and \"%s\" add resources for the same path ".
            "\"%s\", but have no override order defined between them.\n\n".
            "Resolutions:\n\n(1) Add the key \"override\" to the puli.json ".
            "of one package and set its value to the other package name.\n(2) ".
            "Add the key \"override-order\" to the puli.json of the root ".
            "package and define the order of the packages there.",
            $conflict->getPackageName1(),
            $conflict->getPackageName2(),
            $conflict->getConflictingToken()
        ), $code, $cause);
    }
}
