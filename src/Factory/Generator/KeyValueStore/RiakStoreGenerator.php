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
use Puli\Manager\Assert\Assert;

/**
 * Generates the setup code for a {@link RiakStore}.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RiakStoreGenerator implements ServiceGenerator
{
    private static $defaultOptions = array(
        'host' => '127.0.0.1',
        'port' => 8098,
    );

    /**
     * {@inheritdoc}
     */
    public function generateNewInstance($varName, Method $targetMethod, GeneratorRegistry $generatorRegistry, array $options = array())
    {
        Assert::keyExists($options, 'bucket', 'The "bucket" option is missing.');

        $options = array_replace(self::$defaultOptions, $options);

        Assert::string($options['bucket'], 'The bucket must be a string. Got: %s');
        Assert::string($options['host'], 'The host must be a string. Got: %s');
        Assert::integer($options['port'], 'The port must be an integer. Got: %s');

        $escBucket = var_export($options['bucket'], true);
        $escHost = var_export($options['host'], true);
        $escPort = var_export($options['port'], true);

        $targetMethod->getClass()->addImports(array(
            new Import('Basho\Riak\Riak'),
            new Import('Webmozart\KeyValueStore\RiakStore'),
        ));

        $targetMethod->addBody(
<<<EOF
\$client = new Riak($escHost, $escPort);
\$$varName = new RiakStore($escBucket, \$client);
EOF
        );
    }
}
