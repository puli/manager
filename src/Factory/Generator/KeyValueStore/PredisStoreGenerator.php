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
use Puli\RepositoryManager\Assert\Assert;

/**
 * Generates the setup code for a {@link PredisStore}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PredisStoreGenerator implements ServiceGenerator
{
    private static $defaultOptions = array(
        'host' => '127.0.0.1',
        'port' => 6379,
    );

    /**
     * {@inheritdoc}
     */
    public function generateNewInstance($varName, Method $targetMethod, GeneratorRegistry $generatorRegistry, array $options = array())
    {
        $options = array_replace(self::$defaultOptions, $options);

        Assert::string($options['host'], 'The host must be a string. Got: %s');
        Assert::integer($options['port'], 'The port must be an integer. Got: %s');

        $escHost = var_export($options['host'], true);
        $escPort = var_export($options['port'], true);

        $targetMethod->getClass()->addImports(array(
            new Import('Predis\Client'),
            new Import('Webmozart\KeyValueStore\PredisStore'),
        ));

        $targetMethod->addBody(
<<<EOF
\$client = new Client(array(
    'host' => $escHost,
    'port' => $escPort,
));
\$$varName = new PredisStore(\$client);
EOF
        );
    }
}
