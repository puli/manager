<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Repository;

use Exception;
use RuntimeException;

/**
 * Thrown when two packages have conflicting resource mappings.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResourceConflictException extends RuntimeException
{
    public static function forConflict(ResourceConflict $conflict, $code = 0, Exception $cause = null)
    {
        return new static(sprintf(
            "The packages \"%s\" and \"%s\" add resources for the same path ".
            "\"%s\", but have no override order defined between them.\n\n".
            "Resolutions:\n\n(1) Add the key \"override\" to the composer.json ".
            "of one package and set its value to the other package name.\n(2) ".
            "Add the key \"override-order\" to the composer.json of the root ".
            "package and define the order of the packages there.",
            $conflict->getPackageName1(),
            $conflict->getPackageName2(),
            $conflict->getConflictingPath()
        ), $code, $cause);
    }
}
