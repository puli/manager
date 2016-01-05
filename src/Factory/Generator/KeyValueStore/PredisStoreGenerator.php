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
 * Generates the setup code for a {@link PredisStore}.
 *
 * @since  1.0
 *
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

        Assert::stringNotEmpty($options['host'], 'The "host" option must be a non-empty string. Got: %s');
        Assert::integer($options['port'], 'The "port" option must be an integer. Got: %s');

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
