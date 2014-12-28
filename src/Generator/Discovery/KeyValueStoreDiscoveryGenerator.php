<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Generator\Discovery;

use Puli\RepositoryManager\Generator\FactoryCode;
use Puli\RepositoryManager\Generator\FactoryCodeGenerator;
use Puli\RepositoryManager\Generator\GeneratorFactory;

/**
 * Generates the factory code for a key-value store discovery.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class KeyValueStoreDiscoveryGenerator implements FactoryCodeGenerator
{
    private static $defaultOptions = array(
        'store' => array(
            'type' => 'null',
        )
    );

    /**
     * {@inheritdoc}
     */
    public function generateFactoryCode($varName, $outputDir, $rootDir, array $options, GeneratorFactory $generatorFactory)
    {
        $options = array_replace_recursive(self::$defaultOptions, $options);

        $kvsGenerator = $generatorFactory->createKeyValueStoreGenerator($options['store']['type']);
        $kvsCode = $kvsGenerator->generateFactoryCode(
            '$store',
            $outputDir,
            $rootDir,
            $options['store'],
            $generatorFactory
        );

        $code = new FactoryCode();
        $code->addImports($kvsCode->getImports());
        $code->addVarDeclarations($kvsCode->getVarDeclarations());

        $code->addImport('Puli\Discovery\KeyValueStoreDiscovery');
        $code->addVarDeclaration($varName, <<<EOF
$varName = new KeyValueStoreDiscovery(
    \$repo,
    \$store
);
EOF
        );

        return $code;
    }
}
