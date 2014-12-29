<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Generator\KeyValueStore;

use Puli\RepositoryManager\Generator\FactoryCode;
use Puli\RepositoryManager\Generator\FactoryCodeGenerator;
use Puli\RepositoryManager\Generator\GeneratorFactory;
use Webmozart\PathUtil\Path;

/**
 * Generates the factory code for a key-value store backed by the Flintstone
 * library.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FlintstoneStoreGenerator implements FactoryCodeGenerator
{
    private static $defaultOptions = array(
        'path' => 'data.dat',
        'gzip' => false,
        'cache' => true,
        'swapMemoryLimit' => 1048576,
    );

    /**
     * {@inheritdoc}
     */
    public function generateFactoryCode($varName, $outputDir, $rootDir, array $options, GeneratorFactory $generatorFactory)
    {
        $options = array_replace(self::$defaultOptions, $options);

        $code = new FactoryCode();
        $code->addImport('Webmozart\KeyValueStore\FlintstoneStore');
        $code->addImport('Flintstone\FlintstoneDB');

        $dbPath = Path::makeAbsolute($options['path'], $rootDir);
        $dbExtension = pathinfo($dbPath, PATHINFO_EXTENSION);

        // pathinfo() does not include the leading dot that we need
        if ($dbExtension) {
            $dbExtension = '.'.$dbExtension;
        }

        $dbDir = Path::getDirectory($dbPath);
        $relDbDir = Path::makeRelative($dbDir, $outputDir);

        $escDbName = var_export(basename($dbPath, $dbExtension), true);
        $escDbExt = var_export($dbExtension, true);
        $escDbDir = $relDbDir
            ? '__DIR__.'.var_export('/'.$relDbDir, true)
            : '__DIR__';
        $escGzip = $options['gzip'] ? 'true' : 'false';
        $escCache = $options['cache'] ? 'true' : 'false';
        $escMemLimit = var_export($options['swapMemoryLimit'], true);

        $code->addVarDeclaration($varName, <<<EOF
$varName = new FlintstoneStore(
    new FlintstoneDB($escDbName, array(
        'dir' => $escDbDir,
        'ext' => $escDbExt,
        'gzip' => $escGzip,
        'cache' => $escCache,
        'swap_memory_limit' => $escMemLimit,
    ))
);
EOF
        );

        return $code;
    }
}
