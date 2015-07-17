<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Event;

use Puli\Manager\Api\Php\Clazz;
use Symfony\Component\EventDispatcher\Event;

/**
 * Dispatched when the factory class is generated.
 *
 * This event is dispatched before the factory class is written to a file.
 * You can listen to the event to add custom code to the factory class.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GenerateFactoryEvent extends Event
{
    /**
     * @var Clazz
     */
    private $factoryClass;

    /**
     * Creates the event.
     *
     * @param Clazz $factoryClass The factory class.
     */
    public function __construct(Clazz $factoryClass)
    {
        $this->factoryClass = $factoryClass;
    }

    /**
     * Returns the factory class.
     *
     * @return Clazz The factory class.
     */
    public function getFactoryClass()
    {
        return $this->factoryClass;
    }
}
