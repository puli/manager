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
 * Generates the factory code for a key-value store backed by Memcache.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MemcacheStoreGenerator implements FactoryCodeGenerator
{
    private static $defaultOptions = array(
        'server' => '127.0.0.1',
        'port' => 11211,
    );

    /**
     * {@inheritdoc}
     */
    public function generateFactoryCode($varName, $outputDir, $rootDir, array $options, GeneratorFactory $generatorFactory)
    {
        $options = array_replace(self::$defaultOptions, $options);

        $escServer = var_export($options['server'], true);
        $escPort = var_export($options['port'], true);

        $code = new FactoryCode();
        $code->addImport('Memcache');
        $code->addImport('Webmozart\KeyValueStore\Impl\MemcacheStore');
        $code->addVarDeclaration('$memcache', <<<EOF
\$memcache = new Memcache();
\$memcache->connect($escServer, $escPort);
EOF
        );
        $code->addVarDeclaration($varName, $varName.' = new MemcacheStore($memcache);');

        return $code;
    }
}
