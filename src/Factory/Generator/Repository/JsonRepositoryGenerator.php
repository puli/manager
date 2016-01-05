<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Factory\Generator\Repository;

use Puli\Manager\Api\Factory\Generator\GeneratorRegistry;
use Puli\Manager\Api\Factory\Generator\ServiceGenerator;
use Puli\Manager\Api\Php\Import;
use Puli\Manager\Api\Php\Method;
use Puli\Manager\Assert\Assert;
use Webmozart\PathUtil\Path;

/**
 * Generates the setup code for a {@link JsonRepository}.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class JsonRepositoryGenerator implements ServiceGenerator
{
    private static $defaultOptions = array(
        'optimize' => false,
        'change-stream' => array(
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

        if (!isset($options['path'])) {
            $options['path'] = $targetMethod->getClass()->getDirectory().'/path-mappings.json';
        }

        Assert::stringNotEmpty($options['path'], 'The "path" option should be a non-empty string. Got: %s');
        Assert::stringNotEmpty($options['root-dir'], 'The "root-dir" option should be a non-empty string. Got: %s');
        Assert::boolean($options['optimize'], 'The "optimize" option should be a boolean. Got: %s');
        Assert::isArray($options['change-stream'], 'The "change-stream" option should be an array. Got: %s');

        $path = Path::makeAbsolute($options['path'], $options['root-dir']);
        $relPath = Path::makeRelative($path, $targetMethod->getClass()->getDirectory());
        $relBaseDir = Path::makeRelative($options['root-dir'], $targetMethod->getClass()->getDirectory());

        $escPath = '__DIR__.'.var_export('/'.$relPath, true);
        $escBaseDir = $relBaseDir
            ? '__DIR__.'.var_export('/'.$relBaseDir, true)
            : '__DIR__';

        if ($options['optimize']) {
            $streamGenerator = $generatorRegistry->getServiceGenerator(GeneratorRegistry::CHANGE_STREAM, $options['change-stream']['type']);
            $streamOptions = $options['change-stream'];
            $streamOptions['root-dir'] = $options['root-dir'];
            $streamGenerator->generateNewInstance('stream', $targetMethod, $generatorRegistry, $streamOptions);

            $targetMethod->getClass()->addImport(new Import('Puli\\Repository\\OptimizedJsonRepository'));

            $targetMethod->addBody(sprintf(
                '$%s = new OptimizedJsonRepository(%s, %s, $stream);',
                $varName,
                $escPath,
                $escBaseDir
            ));
        } else {
            $targetMethod->getClass()->addImport(new Import('Puli\\Repository\\JsonRepository'));

            $targetMethod->addBody(sprintf(
                '$%s = new JsonRepository(%s, %s);',
                $varName,
                $escPath,
                $escBaseDir
            ));
        }
    }
}
