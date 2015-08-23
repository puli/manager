<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api;

/**
 * Contains the environment constants that Puli operates in.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class Environment
{
    /**
     * The development environment.
     */
    const DEV = 'dev';

    /**
     * The production environment.
     */
    const PROD = 'prod';

    /**
     * Returns all environment names.
     *
     * @return string[] The environment names.
     */
    public static function all()
    {
        return array(
            self::DEV,
            self::PROD,
        );
    }

    /**
     * Must not be instantiated.
     */
    private function __construct()
    {
    }
}
