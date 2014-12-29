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
 * Provides build recipes for a service.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface BuildRecipeProvider
{
    /**
     * Returns the build recipe for a service.
     *
     * @param string          $varName         The name of the variable that
     *                                         the service instance is assigned
     *                                         to.
     * @param string          $outputDir       The directory where the file
     *                                         holding the generated source
     *                                         code is placed. This directory
     *                                         can be accessed with the
     *                                         "__DIR__" constant in the build
     *                                         recipe.
     * @param string          $rootDir         The root directory of the
     *                                         project. All paths passed in
     *                                         the options are relative to this
     *                                         directory.
     * @param array           $options         Additional options needed by the
     *                                         generator.
     * @param ProviderFactory $providerFactory The provider factory.
     *
     * @return BuildRecipe The build recipe for the service.
     *
     * @see BuildRecipe
     */
    public function getRecipe($varName, $outputDir, $rootDir, array $options, ProviderFactory $providerFactory);
}
