<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Util;

use OutOfBoundsException;
use Puli\Manager\Assert\Assert;

/**
 * A LDAPv3 Distinguished Name.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @link   https://www.ietf.org/rfc/rfc2253.txt
 */
class DistinguishedName
{
    /**
     * @var array
     */
    private $attributes = array();

    /**
     * Creates a distinguished name.
     *
     * @param array $attributes The attribute values indexed by the attribute
     *                          names.
     */
    public function __construct(array $attributes = array())
    {
        $this->merge($attributes);
    }

    /**
     * Adds an attribute to the name.
     *
     * @param string $name  The attribute name. Must start with a letter and
     *                      contain letters, digits and hyphens only.
     * @param string $value The attribute value. Any non-empty string is
     *                      allowed.
     *
     * @see merge()
     */
    public function add($name, $value)
    {
        Assert::string($name, 'The attribute name must be a string. Got: %s');
        Assert::notEmpty($name, 'The attribute name must not be empty.');
        Assert::startsWithLetter($name, 'The attribute name %s must start with a letter.');
        Assert::true((bool) preg_match('~^[a-zA-Z][a-zA-Z0-9\-]*$~', $name), sprintf(
            'The attribute name must contain letters, numbers and hyphens only. Got: "%s"',
            $name
        ));
        Assert::string($value, 'The attribute value must be a string. Got: %s');

        $this->attributes[$name] = $value;
    }

    /**
     * Adds multiple attributes to the name.
     *
     * @param array $attributes The attributes to add.
     *
     * @see add()
     */
    public function merge(array $attributes)
    {
        foreach ($attributes as $name => $value) {
            $this->add($name, $value);
        }
    }

    /**
     * Removes an attribute.
     *
     * @param string $name The attribute name.
     *
     * @see add()
     */
    public function remove($name)
    {
        unset($this->attributes[$name]);
    }

    /**
     * Returns the value of an attribute.
     *
     * @param string $name The attribute name.
     *
     * @return string The attribute value.
     *
     * @see add(), has()
     */
    public function get($name)
    {
        if (!isset($this->attributes[$name])) {
            throw new OutOfBoundsException(sprintf(
                'The attribute "%s" was not defined.',
                $name
            ));
        }

        return $this->attributes[$name];
    }

    /**
     * Returns whether an attribute is set.
     *
     * @param string $name The attribute name.
     *
     * @return bool Whether the attribute was set.
     *
     * @see add(), get()
     */
    public function has($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Returns the attributes as array.
     *
     * @return string[] The attribute values indexed by the attribute names.
     */
    public function toArray()
    {
        return $this->attributes;
    }

    /**
     * Returns the distinguished name as string.
     *
     * Attribute values are always enquoted with '"'. Quotation marks and
     * backslashes are escaped with a prefix backslash in the values.
     *
     * @return string The string form of the distinguished name.
     */
    public function toString()
    {
        $stringAttr = array();

        foreach ($this->attributes as $name => $value) {
            $stringAttr[] = $name.'='.$this->escape($value);
        }

        return implode(',', $stringAttr);
    }

    /**
     * @see toString()
     */
    public function __toString()
    {
        return $this->toString();
    }

    private function escape($value)
    {
        // see https://www.ietf.org/rfc/rfc2253.txt

        // We quote the string with '"', thus we can ignore special characters.
        // From the RFC:

        // string     = *( stringchar / pair )
        //              / "#" hexstring
        //              / QUOTATION *( quotechar / pair ) QUOTATION ; only from v2
        // quotechar  = <any character except "\" or QUOTATION >
        // QUOTATION  = <the ASCII double quotation mark character '"' decimal 34>

        return '"'.str_replace(array('\\', '"'), array('\\\\', '\\"'), $value).'"';
    }
}
