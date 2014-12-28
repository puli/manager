<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Generator\Repository;

use Puli\RepositoryManager\Generator\FactoryCode;
use Puli\RepositoryManager\Generator\FactoryCodeGenerator;
use Puli\RepositoryManager\Generator\GeneratorFactory;
use Webmozart\PathUtil\Path;

/**
 * Generates the factory code for a file-copy based repository.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FileCopyRepositoryGenerator implements FactoryCodeGenerator
{
    private static $defaultOptions = array(
        'versionStore' => array(
            'type' => 'null',
        )
    );

    /**
     * {@inheritdoc}
     */
    public function generateFactoryCode($varName, $outputDir, $rootDir, array $options, GeneratorFactory $generatorFactory)
    {
        $options = array_replace_recursive(self::$defaultOptions, $options);

        if (!isset($options['storageDir'])) {
            $options['storageDir'] = $outputDir.'/repository';
        }

        $kvsGenerator = $generatorFactory->createKeyValueStoreGenerator($options['versionStore']['type']);
        $kvsCode = $kvsGenerator->generateFactoryCode('$versionStore', $outputDir, $rootDir, $options['versionStore'], $generatorFactory);

        $storageDir = Path::makeAbsolute($options['storageDir'], $rootDir);
        $relStorageDir = Path::makeRelative($storageDir, $outputDir);

        $escStorageDir = $relStorageDir
            ? '__DIR__.'.var_export('/'.$relStorageDir, true)
            : '__DIR__';

        $code = new FactoryCode();
        $code->addImports($kvsCode->getImports());
        $code->addVarDeclarations($kvsCode->getVarDeclarations());

        $code->addImport('Puli\Repository\FileCopyRepository');
        $code->addVarDeclaration($varName, <<<EOF
$varName = new FileCopyRepository(
    $escStorageDir,
    \$versionStore
);
EOF
        );

        return $code;
    }
}
