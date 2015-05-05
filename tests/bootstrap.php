<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use SebastianBergmann\Comparator\Factory;
use Webmozart\Expression\PhpUnit\ExpressionComparator;

require_once __DIR__.'/../vendor/autoload.php';

Factory::getInstance()->register(new ExpressionComparator());
