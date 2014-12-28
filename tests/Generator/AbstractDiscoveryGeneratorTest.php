<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Generator;

use Puli\RepositoryManager\Generator\FactoryCode;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractDiscoveryGeneratorTest extends AbstractGeneratorTest
{
    protected function putCode($path, FactoryCode $code)
    {
        $codeWithRepo = new FactoryCode();
        $codeWithRepo->addImport('Puli\RepositoryManager\Tests\Generator\Fixtures\TestRepository');
        $codeWithRepo->addImports($code->getImports());
        $codeWithRepo->addVarDeclaration('$repo', '$repo = new TestRepository();');
        $codeWithRepo->addVarDeclarations($code->getVarDeclarations());

        parent::putCode($path, $codeWithRepo);
    }
}
