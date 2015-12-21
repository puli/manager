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
 * Generates the setup code for a {@link PathMappingRepository}.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PathMappingRepositoryGenerator implements ServiceGenerator
{
    private static $defaultOptions = array(
        'store' => array(
            'type' => 'null',
        ),
        'optimize' => false,
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

        if ('json-file' === $kvsOptions['type']) {
            $kvsOptions['serializeStrings'] = false;
            $kvsOptions['serializeArrays'] = false;
            $kvsOptions['escapeSlash'] = false;
            $kvsOptions['prettyPrint'] = true;
        }

        $kvsGenerator->generateNewInstance('store', $targetMethod, $generatorRegistry, $kvsOptions);

        $relPath = Path::makeRelative($options['rootDir'], $targetMethod->getClass()->getDirectory());

        $escPath = $relPath
            ? '__DIR__.'.var_export('/'.$relPath, true)
            : '__DIR__';

        $className = ($options['optimize'] ? 'Optimized' : '').'PathMappingRepository';

        $targetMethod->getClass()->addImport(new Import('Puli\\Repository\\'.$className));

        $targetMethod->addBody(sprintf('$%s = new %s($store, %s);', $varName, $className, $escPath));
    }
}
