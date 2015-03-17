<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Factory\Generator\Discovery;

use Puli\RepositoryManager\Api\Factory\Generator\GeneratorRegistry;
use Puli\RepositoryManager\Api\Factory\Generator\ServiceGenerator;
use Puli\RepositoryManager\Api\Php\Import;
use Puli\RepositoryManager\Api\Php\Method;
use Puli\RepositoryManager\Assert\Assert;

/**
 * Generates the setup code for a {@link KeyValueStoreDiscovery}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class KeyValueStoreDiscoveryGenerator implements ServiceGenerator
{
    private static $defaultOptions = array(
        'store' => array(
            'type' => 'null',
        )
    );

    /**
     * {@inheritdoc}
     */
    public function generateNewInstance($varName, Method $targetMethod, GeneratorRegistry $generatorRegistry, array $options = array())
    {
        Assert::keyExists($options, 'rootDir', 'The "rootDir" option is missing.');

        $options = array_replace_recursive(self::$defaultOptions, $options);

        $kvsGenerator = $generatorRegistry->getServiceGenerator(GeneratorRegistry::KEY_VALUE_STORE, $options['store']['type']);
        $kvsOptions = $options['store'];
        $kvsOptions['rootDir'] = $options['rootDir'];
        $kvsGenerator->generateNewInstance('store', $targetMethod, $generatorRegistry, $kvsOptions);

        $targetMethod->getClass()->addImport(new Import('Puli\Discovery\KeyValueStoreDiscovery'));

        $targetMethod->addBody('$'.$varName.' = new KeyValueStoreDiscovery($repo, $store);');
    }
}
