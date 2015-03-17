<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Php;

use Puli\Manager\Api\Php\Clazz;
use Puli\Manager\Assert\Assert;

/**
 * Writes {@link Clazz} instances to files.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ClassWriter
{
    /**
     * Writes a {@link Clazz} instance to a file.
     *
     * The directory of the class must have been set.
     *
     * @param Clazz $class The class to write.
     */
    public function writeClass(Clazz $class)
    {
        Assert::notEmpty($class->getDirectory(), 'The directory of the written class must not be empty.');

        ob_start();

        require __DIR__.'/../../res/template/Class.tpl.php';

        $source = "<?php\n".ob_get_clean();

        if (!file_exists($class->getDirectory())) {
            mkdir($class->getDirectory(), 0777, true);
        }

        file_put_contents($class->getFilePath(), $source);
    }
}
