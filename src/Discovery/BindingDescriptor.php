<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Discovery;

use InvalidArgumentException;
use Puli\Discovery\Api\NoSuchParameterException;
use Puli\RepositoryManager\Assert\Assertion;
use Puli\RepositoryManager\Util\DistinguishedName;
use Rhumsaa\Uuid\Uuid;

/**
 * Describes a resource binding.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @see    ResourceBinding
 */
class BindingDescriptor
{
    /**
     * @var Uuid
     */
    private $uuid;

    /**
     * @var string
     */
    private $query;

    /**
     * @var string
     */
    private $language;

    /**
     * @var string
     */
    private $typeName;

    /**
     * @var array
     */
    private $parameters;

    /**
     * Creates a new binding descriptor with a generated UUID.
     *
     * The UUID is generated based on the given parameters.
     *
     * @param string $query      The query for the resources of the binding.
     * @param string $typeName   The name of the binding type.
     * @param array  $parameters The values of the binding parameters.
     * @param string $language   The language of the query.
     *
     * @return static The created binding descriptor.
     *
     * @see ResourceBinding
     */
    public static function create($query, $typeName, array $parameters = array(), $language = 'glob')
    {
        Assertion::query($query);
        Assertion::typeName($typeName);
        Assertion::language($language);
        Assertion::allParameterName(array_keys($parameters));
        Assertion::allParameterValue($parameters);

        $dn = new DistinguishedName(array(
            'q' => $query,
            'l' => $language,
            't' => $typeName,
        ));

        foreach ($parameters as $parameter => $value) {
            // Attribute values must be strings
            $dn->add('p-'.$parameter, serialize($value));
        }

        $uuid = Uuid::uuid5(Uuid::NAMESPACE_X500, $dn->toString());

        return new static($uuid, $query, $typeName, $parameters, $language);
    }

    /**
     * Creates a new binding descriptor.
     *
     * @param Uuid   $uuid       The UUID of the binding.
     * @param string $query      The query for the resources of the binding.
     * @param string $typeName   The name of the binding type.
     * @param array  $parameters The values of the binding parameters.
     * @param string $language   The language of the query.
     *
     * @throws InvalidArgumentException If any of the arguments is invalid.
     *
     * @see ResourceBinding
     */
    public function __construct(Uuid $uuid, $query, $typeName, array $parameters = array(), $language = 'glob')
    {
        Assertion::query($query);
        Assertion::typeName($typeName);
        Assertion::language($language);
        Assertion::allParameterName(array_keys($parameters));
        Assertion::allParameterValue($parameters);

        $this->uuid = $uuid;
        $this->query = $query;
        $this->language = $language;
        $this->typeName = $typeName;
        $this->parameters = $parameters;
    }

    /**
     * Returns the UUID of the binding.
     *
     * @return Uuid The universally unique ID.
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * Returns the query for the resources of the binding.
     *
     * @return string The resource query.
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Returns the language of the query.
     *
     * @return string The query language.
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Returns the name of the binding type.
     *
     * @return string The type name.
     */
    public function getTypeName()
    {
        return $this->typeName;
    }

    /**
     * Returns the values of the binding parameters.
     *
     * @return array The parameter values.
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Returns whether the descriptor has any parameter values set.
     *
     * @return bool Returns `true` if any parameter values are set.
     */
    public function hasParameters()
    {
        return count($this->parameters) > 0;
    }

    /**
     * Returns the value of a specific binding parameter.
     *
     * @param string $name The name of the binding parameter.
     *
     * @return mixed The parameter value.
     *
     * @throws NoSuchParameterException If the parameter does not exist.
     */
    public function getParameter($name)
    {
        if (!isset($this->parameters[$name])) {
            throw new NoSuchParameterException(sprintf(
                'The parameter "%s" does not exist.',
                $name
            ));
        }

        return $this->parameters[$name];
    }

    /**
     * Returns whether the descriptor contains a value for a binding parameter.
     *
     * @param string $name The name of the binding parameter.
     *
     * @return bool Returns `true` if a value is set for the parameter.
     */
    public function hasParameter($name)
    {
        return isset($this->parameters[$name]);
    }
}
