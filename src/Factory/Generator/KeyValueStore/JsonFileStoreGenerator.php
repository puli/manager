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
        'serialize-strings' => true,
        'serialize-arrays' => true,
        'escape-slash' => true,
        'pretty-print' => false,
    );

    /**
     * {@inheritdoc}
     */
    public function generateNewInstance($varName, Method $targetMethod, GeneratorRegistry $generatorRegistry, array $options = array())
    {
        Assert::keyExists($options, 'root-dir', 'The "root-dir" option is missing.');

        $options = array_replace(self::$defaultOptions, $options);

        Assert::stringNotEmpty($options['path'], 'The "path" option should be a non-empty string. Got: %s');
        Assert::stringNotEmpty($options['root-dir'], 'The "root-dir" option should be a non-empty string. Got: %s');
        Assert::boolean($options['serialize-strings'], 'The "serialize-strings" option should be a boolean. Got: %s');
        Assert::boolean($options['serialize-arrays'], 'The "serialize-arrays" option should be a boolean. Got: %s');
        Assert::boolean($options['escape-slash'], 'The "escape-slash" option should be a boolean. Got: %s');
        Assert::boolean($options['pretty-print'], 'The "pretty-print" option should be a boolean. Got: %s');

        $path = Path::makeAbsolute($options['path'], $options['root-dir']);
        $relPath = Path::makeRelative($path, $targetMethod->getClass()->getDirectory());

        $flags = array();

        if (!$options['serialize-strings']) {
            $flags[] = 'JsonFileStore::NO_SERIALIZE_STRINGS';
        }

        if (!$options['serialize-arrays']) {
            $flags[] = 'JsonFileStore::NO_SERIALIZE_ARRAYS';
        }

        if (!$options['serialize-arrays']) {
            $flags[] = 'JsonFileStore::NO_ESCAPE_SLASH';
        }

        if ($options['pretty-print']) {
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
