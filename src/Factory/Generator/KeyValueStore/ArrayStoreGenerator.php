<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Factory\Generator\KeyValueStore;

use Puli\RepositoryManager\Api\Factory\Generator\GeneratorRegistry;
use Puli\RepositoryManager\Api\Factory\Generator\ServiceGenerator;
use Puli\RepositoryManager\Api\Php\Import;
use Puli\RepositoryManager\Api\Php\Method;

/**
 * Generates the setup code for an {@link ArrayStore}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ArrayStoreGenerator implements ServiceGenerator
{
    /**
     * {@inheritdoc}
     */
    public function generateNewInstance($varName, Method $targetMethod, GeneratorRegistry $generatorRegistry, array $options = array())
    {
        $targetMethod->getClass()->addImport(new Import('Webmozart\KeyValueStore\ArrayStore'));

        $targetMethod->addBody('$'.$varName.' = new ArrayStore();');
    }
}
