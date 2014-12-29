<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Generator\Fixtures;

use Puli\RepositoryManager\Generator\FactoryCode;
use Puli\RepositoryManager\Generator\FactoryCodeGenerator;
use Puli\RepositoryManager\Generator\GeneratorFactory;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestRepositoryGenerator implements FactoryCodeGenerator
{
    public function generateFactoryCode($varName, $outputDir, $rootDir, array $options, GeneratorFactory $generatorFactory)
    {
        $code = new FactoryCode();
        $code->addImport(__NAMESPACE__.'\TestRepository');
        $code->addVarDeclaration('$path', '$path = "'.$options['path'].'";');
        $code->addVarDeclaration($varName, $varName.' = new TestRepository($path);');

        // Test global imports
        // Global imports need to be filtered when placing code in the global
        // namespace, otherwise PHP creates a fatal error
        $code->addImport('Traversable');

        return $code;
    }
}
