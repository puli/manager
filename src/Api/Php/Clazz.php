<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Php;

use OutOfBoundsException;
use RuntimeException;
use Webmozart\Assert\Assert;
use Webmozart\PathUtil\Path;

/**
 * A model of a class.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Clazz
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
     * @var string
     */
    private $directory;

    /**
     * @var string
     */
    private $fileName;

    /**
     * @var string
     */
    private $parentClass;

    /**
     * @var bool[]
     */
    private $implementedInterfaces = array();

    /**
     * @var Import[]
     */
    private $imports = array();

    /**
     * @var string[]
     */
    private $importedSymbols = array();

    /**
     * @var Method[]
     */
    private $methods = array();

    /**
     * @var string
     */
    private $description;

    /**
     * Creates a new factory class.
     *
     * @param string $className The fully-qualified class name.
     */
    public function __construct($className)
    {
        $this->setClassName($className);
    }

    /**
     * Sets the fully-qualified name of the factory class.
     *
     * @param string $className The fully-qualified class name.
     *
     * @return static The current instance.
     */
    public function setClassName($className)
    {
        Assert::stringNotEmpty($className, 'The class name must be a non-empty string. Got: %s');

        $pos = strrpos($className, '\\');

        if (false === $pos) {
            $this->namespaceName = '';
            $this->shortClassName = $className;
        } else {
            $this->namespaceName = substr($className, 0, $pos);
            $this->shortClassName = substr($className, $pos + 1);
        }

        return $this;
    }

    /**
     * Returns the fully-qualified name of the factory class.
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
     * Returns the namespace of the factory class.
     *
     * @return string The namespace or an empty string if the class is in the
     *                global namespace.
     */
    public function getNamespaceName()
    {
        return $this->namespaceName;
    }

    /**
     * Returns the short class name.
     *
     * @return string The short name of the factory class.
     */
    public function getShortClassName()
    {
        return $this->shortClassName;
    }

    /**
     * Returns the path to the directory holding the factory class file.
     *
     * @return string The absolute directory path.
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * Sets the path to the directory holding the factory class file.
     *
     * @param string $directory The absolute directory path.
     *
     * @return static The current instance.
     */
    public function setDirectory($directory)
    {
        Assert::stringNotEmpty($directory, 'The factory directory must be a non-empty string. Got: %s');

        $this->directory = Path::canonicalize($directory);

        return $this;
    }

    /**
     * Returns the name of the factory class file.
     *
     * If no file name was set, the file is named after the short class name
     * suffixed with ".php".
     *
     * @return string The file name.
     */
    public function getFileName()
    {
        if (!$this->fileName) {
            return $this->shortClassName.'.php';
        }

        return $this->fileName;
    }

    /**
     * Sets the name of the factory class file.
     *
     * @param string $fileName The file name.
     *
     * @return static The current instance.
     */
    public function setFileName($fileName)
    {
        Assert::stringNotEmpty($fileName, 'The factory file name must be a non-empty string. Got: %s');

        $this->fileName = $fileName;

        return $this;
    }

    /**
     * Resets the name of the factory class file to the default value.
     *
     * By default, the file is named after the short class name suffixed with
     * ".php".
     *
     * @return static The current instance.
     */
    public function resetFileName()
    {
        $this->fileName = null;

        return $this;
    }

    /**
     * Returns the absolute file path of the factory class file.
     *
     * @return string The absolute file path.
     */
    public function getFilePath()
    {
        return $this->directory.'/'.$this->getFileName();
    }

    /**
     * Sets the absolute file path of the factory class file.
     *
     * @param string $filePath The absolute file path.
     *
     * @return static The current instance.
     */
    public function setFilePath($filePath)
    {
        Assert::stringNotEmpty($filePath, 'The factory file path must be a non-empty string. Got: %s');

        $this->setDirectory(Path::getDirectory($filePath));
        $this->setFileName(Path::getFilename($filePath));

        return $this;
    }

    /**
     * Returns the parent class name.
     *
     * @return string|null The parent class name or `null` if the class has no
     *                     parent class.
     */
    public function getParentClass()
    {
        return $this->parentClass;
    }

    /**
     * Sets the parent class name.
     *
     * If you don't pass a fully-qualified name, make sure to import the name
     * with {@link addImport()}.
     *
     * @param string $parentClass The parent class name.
     *
     * @return static The current instance.
     */
    public function setParentClass($parentClass)
    {
        Assert::stringNotEmpty($parentClass, 'The parent class name must be a non-empty string. Got: %s');

        $this->parentClass = $parentClass;

        return $this;
    }

    /**
     * Returns whether the class extends another class.
     *
     * @return bool Returns `true` if the class has a parent class and `false`
     *              otherwise.
     */
    public function hasParentClass()
    {
        return null !== $this->parentClass;
    }

    /**
     * Removes the parent class.
     *
     * @return static The current instance.
     */
    public function removeParentClass()
    {
        $this->parentClass = null;

        return $this;
    }

    /**
     * Returns the interfaces that the class implements.
     *
     * @return string[] The names of the implemented interfaces.
     */
    public function getImplementedInterfaces()
    {
        return array_keys($this->implementedInterfaces);
    }

    /**
     * Returns whether the class implements any interfaces.
     *
     * @return bool Returns `true` if the class implements any interfaces and
     *              `false` otherwise.
     */
    public function hasImplementedInterfaces()
    {
        return count($this->implementedInterfaces) > 0;
    }

    /**
     * Sets the interfaces that the class implements.
     *
     * Previously added interfaces are overwritten.
     *
     * If you don't pass fully-qualified names, make sure to import the names
     * with {@link addImport()}.
     *
     * @param string[] $interfaceNames The names of the implemented interfaces.
     *
     * @return static The current instance.
     */
    public function setImplementedInterfaces(array $interfaceNames)
    {
        $this->implementedInterfaces = array();

        $this->addImplementedInterfaces($interfaceNames);

        return $this;
    }

    /**
     * Adds implemented interfaces to the class definition.
     *
     * Previously added interfaces are kept.
     *
     * If you don't pass fully-qualified names, make sure to import the names
     * with {@link addImport()}.
     *
     * @param string[] $interfaceNames The names of the added interfaces.
     *
     * @return static The current instance.
     */
    public function addImplementedInterfaces(array $interfaceNames)
    {
        foreach ($interfaceNames as $interfaceName) {
            $this->addImplementedInterface($interfaceName);
        }

        return $this;
    }

    /**
     * Adds an implemented interface to the class definition.
     *
     * If you don't pass a fully-qualified name, make sure to import the name
     * with {@link addImport()}.
     *
     * @param string $interfaceName The name of the added interfaces.
     *
     * @return static The current instance.
     */
    public function addImplementedInterface($interfaceName)
    {
        Assert::stringNotEmpty($interfaceName, 'The interface name must be a non-empty string. Got: %s');

        $this->implementedInterfaces[$interfaceName] = true;

        return $this;
    }

    /**
     * Removes an implemented interface from the class definition.
     *
     * @param string $interfaceName The name of the removed interfaces.
     *
     * @return static The current instance.
     */
    public function removeImplementedInterface($interfaceName)
    {
        unset($this->implementedInterfaces[$interfaceName]);

        return $this;
    }

    /**
     * Removes all implemented interfaces from the class definition.
     *
     * @return static The current instance.
     */
    public function clearImplementedInterfaces()
    {
        $this->implementedInterfaces = array();

        return $this;
    }

    /**
     * Returns the import statements of the class file.
     *
     * @return Import[] The imported fully-qualified class names.
     */
    public function getImports()
    {
        return $this->imports;
    }

    /**
     * Returns whether the class file imports any class name.
     *
     * @return bool Returns `true` if the class file imports class names and
     *              `false` otherwise.
     */
    public function hasImports()
    {
        return count($this->imports) > 0;
    }

    /**
     * Sets the import statements of the class file.
     *
     * Previously added imports are overwritten.
     *
     * @param Import[] $imports The imported fully-qualified class names.
     *
     * @return static The current instance.
     */
    public function setImports(array $imports)
    {
        $this->imports = array();

        $this->addImports($imports);

        return $this;
    }

    /**
     * Adds import statements to the class file.
     *
     * Previously added imports are kept.
     *
     * @param Import[] $imports The imported fully-qualified class names.
     *
     * @return static The current instance.
     */
    public function addImports(array $imports)
    {
        foreach ($imports as $import) {
            $this->addImport($import);
        }

        return $this;
    }

    /**
     * Adds an import statement to the class file.
     *
     * @param Import $import The imported fully-qualified class name.
     *
     * @return static The current instance.
     */
    public function addImport(Import $import)
    {
        if (isset($this->imports[$import->getClassName()])) {
            return $this;
        }

        $symbol = $import->getAlias() ?: $import->getShortClassName();

        if (isset($this->importedSymbols[$symbol])) {
            throw new RuntimeException(sprintf(
                'The symbol "%s" was imported already.',
                $import->getShortClassName()
            ));
        }

        $this->imports[$import->getClassName()] = $import;
        $this->importedSymbols[$symbol] = true;

        ksort($this->imports);

        return $this;
    }

    /**
     * Removes an import statement from the class file.
     *
     * If the import statement is not found, this method does nothing.
     *
     * @param string $className The removed imported class name.
     *
     * @return static The current instance.
     */
    public function removeImport($className)
    {
        if (isset($this->imports[$className])) {
            $import = $this->imports[$className];
            $symbol = $import->getAlias() ?: $import->getShortClassName();

            unset($this->imports[$className]);
            unset($this->importedSymbols[$symbol]);
        }

        return $this;
    }

    /**
     * Removes all import statements from the class file.
     *
     * @return static The current instance.
     */
    public function clearImports()
    {
        $this->imports = array();

        return $this;
    }

    /**
     * Returns the methods of the factory class.
     *
     * @return Method[] The methods indexed by their names.
     */
    public function getMethods()
    {
        return $this->methods;
    }

    /**
     * Returns the method with the given name.
     *
     * @param string $name The name of the method.
     *
     * @return Method The method.
     *
     * @throws OutOfBoundsException If the method with the given name does not
     *                              exist.
     */
    public function getMethod($name)
    {
        if (!isset($this->methods[$name])) {
            throw new OutOfBoundsException(sprintf(
                'The method "%s" does not exist.',
                $name
            ));
        }

        return $this->methods[$name];
    }

    /**
     * Returns whether the class contains any methods.
     *
     * @return bool Returns `true` if the class contains methods and `false`
     *              otherwise.
     */
    public function hasMethods()
    {
        return count($this->methods) > 0;
    }

    /**
     * Returns whether the class contains a method with the given name.
     *
     * @param string $name The name of the method.
     *
     * @return bool Returns `true` if the method with the given name exists and
     *              `false` otherwise.
     */
    public function hasMethod($name)
    {
        return isset($this->methods[$name]);
    }

    /**
     * Sets the methods of the class.
     *
     * Previously added methods are overwritten.
     *
     * @param Method[] $methods The methods of the class.
     *
     * @return static The current instance.
     */
    public function setMethods(array $methods)
    {
        $this->methods = array();

        $this->addMethods($methods);

        return $this;
    }

    /**
     * Adds methods to the class.
     *
     * Previously added methods are kept.
     *
     * @param Method[] $methods The methods to add to the class.
     *
     * @return static The current instance.
     */
    public function addMethods(array $methods)
    {
        foreach ($methods as $method) {
            $this->addMethod($method);
        }

        return $this;
    }

    /**
     * Adds a method to the class.
     *
     * @param Method $method The method to add to the class.
     *
     * @return static The current instance.
     */
    public function addMethod(Method $method)
    {
        if (isset($this->methods[$method->getName()])) {
            throw new RuntimeException(sprintf(
                'The method "%s" exists already.',
                $method->getName()
            ));
        }

        $this->methods[$method->getName()] = $method;

        $method->setClass($this);

        return $this;
    }

    /**
     * Removes a method from the class.
     *
     * @param string $name The name of the removed method.
     *
     * @return static The current instance.
     */
    public function removeMethod($name)
    {
        unset($this->methods[$name]);

        return $this;
    }

    /**
     * Removes all methods from the class.
     *
     * @return static The current instance.
     */
    public function clearMethods()
    {
        $this->methods = array();

        return $this;
    }

    /**
     * Returns the description of the class.
     *
     * @return string The class description.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Sets the description of the class.
     *
     * @param string $description The class description.
     *
     * @return static The current instance.
     */
    public function setDescription($description)
    {
        Assert::stringNotEmpty($description, 'The class description must be a non-empty string. Got: %s');

        $this->description = $description;

        return $this;
    }
}
