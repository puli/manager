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
        if (!isset($options['path'])) {
            $options['path'] = $outputDir.'/repository';
        }

        $path = Path::makeAbsolute($options['path'], $rootDir);
        $relPath = Path::makeRelative($path, $outputDir);

        $escPath = $relPath
            ? '__DIR__.'.var_export('/'.$relPath, true)
            : '__DIR__';

        $declaration = '';

        if ($relPath) {
            $declaration = "if (!file_exists($escPath)) {\n".
                "    mkdir($escPath, 0777, true);\n".
                "}\n\n";
        }

        $declaration .= "$varName = new FilesystemRepository($escPath);";

        $code = new FactoryCode();
        $code->addImport('Puli\Repository\FilesystemRepository');
        $code->addVarDeclaration($varName, $declaration);

        return $code;
    }
}
