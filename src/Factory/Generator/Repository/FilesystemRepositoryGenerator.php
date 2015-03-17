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
 * Generates the setup code for a {@link FilesystemRepository}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FilesystemRepositoryGenerator implements ServiceGenerator
{
    private static $defaultOptions = array(
        'symlink' => true,
    );

    /**
     * {@inheritdoc}
     */
    public function generateNewInstance($varName, Method $targetMethod, GeneratorRegistry $generatorRegistry, array $options = array())
    {
        Assert::keyExists($options, 'rootDir', 'The "rootDir" option is missing.');

        $options = array_replace(self::$defaultOptions, $options);

        if (!isset($options['path'])) {
            $options['path'] = $targetMethod->getClass()->getDirectory().'/repository';
        }

        Assert::string($options['path'], 'The "path" option should be a string. Got: %s');
        Assert::string($options['rootDir'], 'The "rootDir" option should be a string. Got: %s');
        Assert::boolean($options['symlink'], 'The "symlink" option should be a boolean. Got: %s');

        $path = Path::makeAbsolute($options['path'], $options['rootDir']);
        $relPath = Path::makeRelative($path, $targetMethod->getClass()->getDirectory());

        $escPath = $relPath
            ? '__DIR__.'.var_export('/'.$relPath, true)
            : '__DIR__';

        if ($relPath) {
            $targetMethod->addBody(
<<<EOF
if (!file_exists($escPath)) {
    mkdir($escPath, 0777, true);
}

EOF
            );
        }

        $targetMethod->getClass()->addImport(new Import('Puli\Repository\FilesystemRepository'));

        $targetMethod->addBody(sprintf('$%s = new FilesystemRepository(%s, %s);',
            $varName,
            $escPath,
            var_export($options['symlink'], true)
        ));
    }
}
