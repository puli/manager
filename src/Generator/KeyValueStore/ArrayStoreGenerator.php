<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Generator\KeyValueStore;

use Puli\RepositoryManager\Generator\FactoryCode;
use Puli\RepositoryManager\Generator\FactoryCodeGenerator;
use Puli\RepositoryManager\Generator\GeneratorFactory;
use Webmozart\PathUtil\Path;

/**
 * Generates the factory code for an array-based key-value store.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ArrayStoreGenerator implements FactoryCodeGenerator
{
    /**
     * {@inheritdoc}
     */
    public function generateFactoryCode($varName, $outputDir, $rootDir, array $options, GeneratorFactory $generatorFactory)
    {
        $code = new FactoryCode();
        $code->addImport('Webmozart\KeyValueStore\Impl\ArrayStore');
        $code->addVarDeclaration($varName, $varName.' = new ArrayStore();');

        return $code;
    }
}
