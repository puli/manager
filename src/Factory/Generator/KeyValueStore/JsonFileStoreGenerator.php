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
use Webmozart\PathUtil\Path;

/**
 * Generates the setup code for a {@link JsonFileStore}.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class JsonFileStoreGenerator implements ServiceGenerator
{
    private static $defaultOptions = array(
        'path' => 'data.json',
        'serializeStrings' => true,
        'serializeArrays' => true,
        'escapeSlash' => true,
        'prettyPrint' => false,
    );

    /**
     * {@inheritdoc}
     */
    public function generateNewInstance($varName, Method $targetMethod, GeneratorRegistry $generatorRegistry, array $options = array())
    {
        Assert::keyExists($options, 'rootDir', 'The "rootDir" option is missing.');

        $options = array_replace(self::$defaultOptions, $options);

        $path = Path::makeAbsolute($options['path'], $options['rootDir']);
        $relPath = Path::makeRelative($path, $targetMethod->getClass()->getDirectory());

        $flags = array();

        if (!$options['serializeStrings']) {
            $flags[] = 'JsonFileStore::NO_SERIALIZE_STRINGS';
        }

        if (!$options['serializeArrays']) {
            $flags[] = 'JsonFileStore::NO_SERIALIZE_ARRAYS';
        }

        if (!$options['serializeArrays']) {
            $flags[] = 'JsonFileStore::NO_ESCAPE_SLASH';
        }

        if ($options['prettyPrint']) {
            $flags[] = 'JsonFileStore::PRETTY_PRINT';
        }

        $targetMethod->getClass()->addImport(new Import('Webmozart\KeyValueStore\JsonFileStore'));

        $targetMethod->addBody(sprintf('$%s = new JsonFileStore(%s%s%s);',
            $varName,
            $flags ? "\n    " : '',
            '__DIR__.'.var_export('/'.$relPath, true),
            $flags ? ",\n    ".implode("\n        | ", $flags)."\n" : ''
        ));
    }
}
