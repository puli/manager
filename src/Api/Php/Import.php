<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Php;

use Puli\RepositoryManager\Assert\Assert;

/**
 * An import statement of a {@link Clazz} file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Import
{
    /**
     * @var string
     */
    private $namespaceName;

    /**
     * @var string
     */
    private $shortClassName;

    /**
     * @var string|null
     */
    private $alias;

    /**
     * Creates the import statement.
     *
     * @param string      $className The fully-qualified imported class name.
     * @param string|null $alias     If not `null`, the class will be imported
     *                               with the given alias.
     */
    public function __construct($className, $alias = null)
    {
        Assert::stringNotEmpty($className, 'The imported class name must be a non-empty string. Got: %s');
        Assert::nullOrStringNotEmpty($className, 'The import alias must be a non-empty string or null. Got: %s');

        $pos = strrpos($className, '\\');

        if (false === $pos) {
            $this->namespaceName = '';
            $this->shortClassName = $className;
        } else {
            $this->namespaceName = substr($className, 0, $pos);
            $this->shortClassName = substr($className, $pos + 1);
        }

        $this->alias = $alias;
    }

    /**
     * Returns the fully-qualified class name.
     *
     * @return string The fully-qualified class name.
     */
    public function getClassName()
    {
        return $this->namespaceName
            ? $this->namespaceName.'\\'.$this->shortClassName
            : $this->shortClassName;
    }

    /**
     * Returns the namespace of the imported class.
     *
     * @return string The namespace or an empty string if the class is in the
     *                global namespace.
     */
    public function getNamespaceName()
    {
        return $this->namespaceName;
    }

    /**
     * Returns the short class name of the imported class.
     *
     * @return string The short name of the imported class.
     */
    public function getShortClassName()
    {
        return $this->shortClassName;
    }

    /**
     * Returns the alias under which the class is imported.
     *
     * @return string|null The alias or `null` if the class is imported under
     *                     its actual name.
     */
    public function getAlias()
    {
        return $this->alias;
    }


    /**
     * Returns the source code of the import.
     *
     * @return string The source code.
     */
    public function __toString()
    {
        return $this->alias ? $this->getClassName().' as '.$this->alias : $this->getClassName();
    }
}
