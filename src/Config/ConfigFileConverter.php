<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Config;

use Puli\Manager\Api\Config\ConfigFile;
use Puli\Manager\Assert\Assert;
use Webmozart\Json\Conversion\JsonConverter;

/**
 * Converts config files to JSON and back.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigFileConverter implements JsonConverter
{
    /**
     * {@inheritdoc}
     */
    public function toJson($configFile, array $options = array())
    {
        Assert::isInstanceOf($configFile, 'Puli\Manager\Api\Config\ConfigFile');

        $jsonData = array();

        foreach ($configFile->getConfig()->toRawArray(false) as $key => $value) {
            $jsonData[$key] = $value;
        }

        // The topmost array is always an object, even if empty
        return (object) $this->arraysToObjects($jsonData);
    }

    /**
     * {@inheritdoc}
     */
    public function fromJson($jsonData, array $options = array())
    {
        $path = isset($options['path']) ? $options['path'] : null;
        $baseConfig = isset($options['baseConfig']) ? $options['baseConfig'] : null;

        Assert::isInstanceOf($jsonData, 'stdClass');
        Assert::nullOrString($path, 'The "path" option should be null or a string. Got: %s');
        Assert::nullOrIsInstanceOf($baseConfig, 'Puli\Manager\Api\Config\Config', 'The "baseConfig" option should be null or an instance of %2$s. Got: %s');

        $configFile = new ConfigFile($path, $baseConfig);
        $config = $configFile->getConfig();

        $jsonData = $this->objectsToArrays($jsonData);

        foreach ($jsonData as $key => $value) {
            $config->set($key, $value);
        }

        return $configFile;
    }

    private function objectsToArrays($data)
    {
        $data = (array) $data;

        foreach ($data as $key => $value) {
            $data[$key] = is_object($value) ? $this->objectsToArrays($value) : $value;
        }

        return $data;
    }

    private function arraysToObjects(array $data)
    {
        $intKeys = true;

        foreach ($data as $key => $value) {
            $intKeys = $intKeys && is_int($key);
            $data[$key] = is_array($value) ? $this->arraysToObjects($value) : $value;
        }

        return $intKeys ? $data : (object) $data;
    }
}
