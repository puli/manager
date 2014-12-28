<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Generator;

use Assert\Assertion;
use InvalidArgumentException;

/**
 * Contains the factory code of a service.
 *
 * Use {@link addVarDeclaration()} to add the factory code with a given variable
 * name. With {@link addImport()}, you can add import statements for any classes
 * that you use in your code:
 *
 * ```php
 * use Puli\RepositoryManager\Generator\FactoryCode;
 *
 * $factoryCode = new FactoryCode();
 * $factoryCode->addImport('Acme\TypeWriter');
 * $factoryCode->addVarDeclaration('$writer', '$writer = new TypeWriter();');
 * ```
 *
 * If your service depends on other services, add declarations for these
 * services before adding the declaration for the actual service:
 *
 * ```php
 * use Puli\RepositoryManager\Generator\FactoryCode;
 *
 * $factoryCode = new FactoryCode();
 * $factoryCode->addImport('Acme\TypeWriter');
 * $factoryCode->addImport('Acme\InkRefiller');
 * $factoryCode->addVarDeclaration('$refiller', '$refiller = new InkRefiller();');
 * $factoryCode->addVarDeclaration('$writer', '$writer = new TypeWriter($refiller);');
 * ```
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FactoryCode
{
    /**
     * @var bool[]
     */
    private $imports = array();

    /**
     * @var string[]
     */
    private $varDeclarations = array();

    /**
     * Adds an import statement for the given fully-qualified class name.
     *
     * @param string $fqcn The fully-qualified class name.
     */
    public function addImport($fqcn)
    {
        $this->imports[$fqcn] = true;
    }

    /**
     * Adds import statements for a list of fully-qualified class name.s
     *
     * @param string[] $fqcns The fully-qualified class names.
     */
    public function addImports(array $fqcns)
    {
        foreach ($fqcns as $fqcn) {
            $this->addImport($fqcn);
        }
    }

    /**
     * Returns all imported fully-qualified class names.
     *
     * The class names are returned without duplicates and sorted
     * alphabetically.
     *
     * @return string[] The fully-qualified class names.
     */
    public function getImports()
    {
        $imports = array_keys($this->imports);
        sort($imports);

        return $imports;
    }

    /**
     * Adds a variable declaration.
     *
     * @param string $varName The variable name with leading "$".
     * @param string $source  The source code assigning a value to the variable.
     *
     * @throws InvalidArgumentException If any of the arguments is invalid.
     */
    public function addVarDeclaration($varName, $source)
    {
        Assertion::false(isset($this->varDeclarations[$varName]), sprintf(
            'The variable "%s" is already defined.',
            $varName
        ));
        Assertion::startsWith($varName, '$', 'The variable "%s" must start with a "$".');
        Assertion::contains($source, $varName, 'The variable "%2$s" must occur in the source code.');

        $this->varDeclarations[$varName] = $source;
    }

    /**
     * Adds a list of variable declarations.
     *
     * @param string[] $sources A mapping of variable names (with leading "$")
     *                          to code snippets assigning values to those
     *                          variables.
     *
     * @throws InvalidArgumentException If any of the arguments is invalid.
     */
    public function addVarDeclarations(array $sources)
    {
        foreach ($sources as $varName => $source) {
            $this->addVarDeclaration($varName, $source);
        }
    }

    /**
     * Returns all added  variable declarations.
     *
     * @return string[] A mapping of variable names (with leading "$") to code
     *                  snippets assigning values to those variables.
     */
    public function getVarDeclarations()
    {
        return $this->varDeclarations;
    }
}
