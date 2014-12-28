<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Generator;

/**
 * Generates the factory code for a service.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FactoryCodeGenerator
{
    /**
     * Generates the factory code for a service.
     *
     * @param string           $varName          The name of the variable that
     *                                           will hold the service instance.
     * @param string           $outputDir        The directory where the
     *                                           generated source code is
     * @param string           $rootDir          The root directory of the
     *                                           project.
     * @param array            $options          Additional options needed by
     *                                           the generator.
     * @param GeneratorFactory $generatorFactory The generator factory.
     *
     * @return FactoryCode The factory code for the service.
     */
    public function generateFactoryCode($varName, $outputDir, $rootDir, array $options, GeneratorFactory $generatorFactory);
}
