<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Dispatched when JSON data is loaded or saved from/to a JSON file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class JsonEvent extends Event
{
    /**
     * @var string
     */
    private $jsonPath;

    /**
     * @var mixed
     */
    private $jsonData;

    /**
     * Creates the event.
     *
     * @param string $jsonPath The path to the JSON file.
     * @param mixed  $jsonData The decoded JSON data.
     */
    public function __construct($jsonPath, $jsonData)
    {
        $this->jsonPath = $jsonPath;
        $this->jsonData = $jsonData;
    }

    /**
     * Returns the path to the JSON file.
     *
     * @return string The path to the JSON file.
     */
    public function getJsonPath()
    {
        return $this->jsonPath;
    }

    /**
     * Returns the decoded JSON data.
     *
     * @return mixed The decoded JSON data.
     */
    public function getJsonData()
    {
        return $this->jsonData;
    }

    /**
     * Sets the decoded JSON data.
     *
     * @param mixed $jsonData The decoded JSON data.
     */
    public function setJsonData($jsonData)
    {
        $this->jsonData = $jsonData;
    }
}
