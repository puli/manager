<?php

/*
 * This file is part of the webmozart/json package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Json;

use InvalidArgumentException;
use Puli\Manager\Api\Puli;
use Webmozart\Json\Conversion\JsonConverter;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class JsonConverterProvider
{
    /**
     * @var Puli
     */
    private $puli;

    public function __construct(Puli $puli)
    {
        $this->puli = $puli;
    }

    /**
     * @param string $className The name of the class to convert.
     *
     * @return JsonConverter The JSON converter.
     */
    public function getJsonConverter($className)
    {
        switch ($className) {
            case 'Puli\Manager\Api\Config\ConfigFile':
                return $this->puli->getConfigFileConverter();

            case 'Puli\Manager\Api\Module\ModuleFile':
                return $this->puli->getLegacyModuleFileConverter();

            case 'Puli\Manager\Api\Module\RootModuleFile':
                return $this->puli->getLegacyRootModuleFileConverter();

            default:
                throw new InvalidArgumentException(sprintf(
                    'Could not find converter for class "%s".',
                    $className
                ));
        }
    }
}
