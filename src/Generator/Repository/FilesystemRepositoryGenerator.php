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
 * Generates the factory code for a filesystem based repository.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FilesystemRepositoryGenerator implements FactoryCodeGenerator
{
    /**
     * {@inheritdoc}
     */
    public function generateFactoryCode($varName, $outputDir, $rootDir, array $options, GeneratorFactory $generatorFactory)
    {
        if (!isset($options['storageDir'])) {
            $options['storageDir'] = $outputDir.'/repository';
        }

        $storageDir = Path::makeAbsolute($options['storageDir'], $rootDir);
        $relStorageDir = Path::makeRelative($storageDir, $outputDir);

        $escStorageDir = $relStorageDir
            ? '__DIR__.'.var_export('/'.$relStorageDir, true)
            : '__DIR__';

        $declaration = '';

        if ($relStorageDir) {
            $declaration = "if (!file_exists($escStorageDir)) {\n".
                "    mkdir($escStorageDir, 0777, true);\n".
                "}\n\n";
        }

        $declaration .= "$varName = new FilesystemRepository($escStorageDir);";

        $code = new FactoryCode();
        $code->addImport('Puli\Repository\FilesystemRepository');
        $code->addVarDeclaration($varName, $declaration);

        return $code;
    }
}
