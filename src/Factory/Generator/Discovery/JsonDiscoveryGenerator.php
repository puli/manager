<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Factory\Generator\Discovery;

use Puli\Manager\Api\Factory\Generator\GeneratorRegistry;
use Puli\Manager\Api\Factory\Generator\ServiceGenerator;
use Puli\Manager\Api\Php\Import;
use Puli\Manager\Api\Php\Method;
use Puli\Manager\Assert\Assert;
use Webmozart\PathUtil\Path;

/**
 * Generates the setup code for a {@link JsonDiscovery}.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class JsonDiscoveryGenerator implements ServiceGenerator
{
    /**
     * {@inheritdoc}
     */
    public function generateNewInstance($varName, Method $targetMethod, GeneratorRegistry $generatorRegistry, array $options = array())
    {
        Assert::keyExists($options, 'root-dir', 'The "root-dir" option is missing.');

        if (!isset($options['path'])) {
            $options['path'] = $targetMethod->getClass()->getDirectory().'/bindings.json';
        }

        Assert::stringNotEmpty($options['root-dir'], 'The "root-dir" option should be a non-empty string. Got: %s');
        Assert::stringNotEmpty($options['path'], 'The "path" option should be a non-empty string. Got: %s');

        $path = Path::makeAbsolute($options['path'], $options['root-dir']);
        $relPath = Path::makeRelative($path, $targetMethod->getClass()->getDirectory());

        $escPath = '__DIR__.'.var_export('/'.$relPath, true);

        $targetMethod->getClass()->addImport(new Import('Puli\Discovery\JsonDiscovery'));
        $targetMethod->getClass()->addImport(new Import('Puli\Discovery\Binding\Initializer\ResourceBindingInitializer'));

        $targetMethod->addBody(sprintf(
            "$%s = new JsonDiscovery(%s, array(\n    new ResourceBindingInitializer(\$repo),\n));",
            $varName,
            $escPath
        ));
    }
}
