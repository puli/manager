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

use stdClass;
use Webmozart\Json\Versioning\CannotParseVersionException;
use Webmozart\Json\Versioning\CannotUpdateVersionException;
use Webmozart\Json\Versioning\JsonVersioner;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChainVersioner implements JsonVersioner
{
    /**
     * @var JsonVersioner[]
     */
    private $versioners;

    /**
     * @param JsonVersioner[] $versioners
     */
    public function __construct(array $versioners)
    {
        $this->versioners = $versioners;
    }

    /**
     * {@inheritdoc}
     */
    public function parseVersion(stdClass $jsonData)
    {
        /** @var CannotParseVersionException $firstException */
        $firstException = null;

        foreach ($this->versioners as $versioner) {
            try {
                return $versioner->parseVersion($jsonData);
            } catch (CannotParseVersionException $e) {
                if (null === $firstException) {
                    $firstException = $e;
                }
            }
        }

        throw $firstException;
    }

    /**
     * {@inheritdoc}
     */
    public function updateVersion(stdClass $jsonData, $version)
    {
        /** @var CannotUpdateVersionException $firstException */
        $firstException = null;

        foreach ($this->versioners as $versioner) {
            try {
                $versioner->updateVersion($jsonData, $version);

                return;
            } catch (CannotUpdateVersionException $e) {
                if (null === $firstException) {
                    $firstException = $e;
                }
            }
        }

        throw $firstException;
    }
}
