<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Conflict;

use Exception;
use RuntimeException;

/**
 * Thrown when two modules have conflicting path mappings.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleConflictException extends RuntimeException
{
    public static function forPathConflict(ModuleConflict $conflict, Exception $cause = null)
    {
        $moduleNames = $conflict->getModuleNames();
        $lastModuleName = array_pop($moduleNames);

        return new static(sprintf(
            'The modules "%s" and "%s" add resources for the same path '.
            "\"%s\", but have no override order defined between them.\n\n".
            "Resolutions:\n\n(1) Add the key \"override\" to the puli.json ".
            "of one module and set its value to the other module name.\n(2) ".
            'Add the key "override-order" to the puli.json of the root '.
            'module and define the order of the modules there.',
            implode('", "', $moduleNames),
            $lastModuleName,
            $conflict->getConflictingToken()
        ), 0, $cause);
    }
}
