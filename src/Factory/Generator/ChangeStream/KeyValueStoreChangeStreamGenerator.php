<?php

/*
 * This file is part of the vendor/project package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Factory\Generator\ChangeStream;

use Puli\Manager\Api\Factory\Generator\GeneratorRegistry;
use Puli\Manager\Api\Factory\Generator\ServiceGenerator;
use Puli\Manager\Api\Php\Import;
use Puli\Manager\Api\Php\Method;
use Puli\Manager\Assert\Assert;

/**
 * Generates the setup code for a {@link KeyValueStoreChangeStream}.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class KeyValueStoreChangeStreamGenerator implements ServiceGenerator
{
    private static $defaultOptions = array(
        'store' => array(
            'type' => 'json',
        ),
    );

    /**
     * {@inheritdoc}
     */
    public function generateNewInstance($varName, Method $targetMethod, GeneratorRegistry $generatorRegistry, array $options = array())
    {
        Assert::keyExists($options, 'root-dir', 'The "root-dir" option is missing.');

        $options = array_replace_recursive(self::$defaultOptions, $options);

        Assert::stringNotEmpty($options['root-dir'], 'The "root-dir" option should be a non-empty string. Got: %s');
        Assert::isArray($options['store'], 'The "store" option should be an array. Got: %s');

        if (!isset($options['store']['path'])) {
            $options['store']['path'] = $targetMethod->getClass()->getDirectory().'/change-stream.json';
        }

        $kvsGenerator = $generatorRegistry->getServiceGenerator(GeneratorRegistry::KEY_VALUE_STORE, $options['store']['type']);
        $kvsOptions = $options['store'];
        $kvsOptions['root-dir'] = $options['root-dir'];
        $kvsGenerator->generateNewInstance('store', $targetMethod, $generatorRegistry, $kvsOptions);

        $targetMethod->getClass()->addImport(new Import('Puli\Repository\ChangeStream\KeyValueStoreChangeStream'));

        $targetMethod->addBody(sprintf(
            '$%s = new KeyValueStoreChangeStream($store);',
            $varName
        ));
    }
}
