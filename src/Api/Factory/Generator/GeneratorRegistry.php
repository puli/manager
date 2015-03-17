<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Factory\Generator;

/**
 * Provides access to named service generators.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface GeneratorRegistry
{
    /**
     * Type: A generator for implementations of {@link EditableDiscovery}.
     */
    const DISCOVERY = 'discovery';

    /**
     * Type: A generator for implementations of {@link EditableRepository}.
     */
    const REPOSITORY = 'repository';

    /**
     * Type: A generator for implementations of {@link KeyValueStore}.
     */
    const KEY_VALUE_STORE = 'key-value-store';

    /**
     * Returns the generator for the given service name.
     *
     * @param string $type One of the type constants in this interface.
     * @param string $name The name of the service generator.
     *
     * @return ServiceGenerator The service generator for the given name.
     */
    public function getServiceGenerator($type, $name);
}
