<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Factory\Generator;

use Puli\RepositoryManager\Api\Php\Method;

/**
 * Generates the instantiation code for a service.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ServiceGenerator
{
    /**
     * Generates a "new Service()" statement stored in the given variable.
     *
     * The resulting code could look something like this:
     *
     * ```php
     * $dependency = new Dependency();
     * $varName = new Service($dependency);
     * ```
     *
     * This code is added to the method passed in the second parameter.
     *
     * @param string            $varName           The variable name without
     *                                             leading "$".
     * @param Method            $targetMethod      The method in which the code
     *                                             is stored.
     * @param GeneratorRegistry $generatorRegistry The generator registry.
     * @param array             $options           Additional implementation
     *                                             specific options.
     */
    public function generateNewInstance($varName, Method $targetMethod, GeneratorRegistry $generatorRegistry, array $options = array());
}
