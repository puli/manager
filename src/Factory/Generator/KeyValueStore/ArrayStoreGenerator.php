<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Factory\Generator\KeyValueStore;

use Puli\Manager\Api\Factory\Generator\GeneratorRegistry;
use Puli\Manager\Api\Factory\Generator\ServiceGenerator;
use Puli\Manager\Api\Php\Import;
use Puli\Manager\Api\Php\Method;

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
