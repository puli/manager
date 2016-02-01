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

use JsonSchema\Uri\Retrievers\FileGetContents;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LocalUriRetriever extends FileGetContents
{
    /**
     * @var string
     */
    private $baseDir;

    public function __construct()
    {
        $this->baseDir = 'file://'.Path::canonicalize(__DIR__.'/../../res/schema');
    }

    /**
     * {@inheritdoc}
     */
    public function retrieve($uri)
    {
        switch ($uri) {
            case 'http://puli.io/schema/1.0/manager/module':
                $uri = $this->baseDir.'/module-schema-1.0.json';
                break;

            case 'http://puli.io/schema/2.0/manager/module':
                $uri = $this->baseDir.'/module-schema-2.0.json';
                break;

            case 'http://puli.io/schema/1.0/manager/dependencies':
                $uri = $this->baseDir.'/dependencies-schema-1.0.json';
                break;

            default:
                break;
        }

        return parent::retrieve($uri);
    }
}
