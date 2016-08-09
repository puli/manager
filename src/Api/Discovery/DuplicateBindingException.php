<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Discovery;

use Exception;
use Puli\Discovery\Api\Binding\Binding;
use RuntimeException;

/**
 * Thrown when a duplicate binding is detected.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DuplicateBindingException extends RuntimeException
{
    /**
     * Creates an exception for a binding.
     *
     * @param Binding        $binding The binding.
     * @param Exception|null $cause   The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function forBinding(Binding $binding, Exception $cause = null)
    {
        return new static(sprintf(
            'The binding of type "%s" is already defined.',
            get_class($binding)
        ), 0, $cause);
    }
}
