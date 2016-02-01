<?php

/*
 * This file is part of the webmozart/json package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Module\Migration;

use Puli\Manager\Module\ModuleFileConverter;
use stdClass;
use Webmozart\Json\Migration\JsonMigration;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleFile10To20Migration implements JsonMigration
{
    public function getSourceVersion()
    {
        return '1.0';
    }

    public function getTargetVersion()
    {
        return '2.0';
    }

    public function up(stdClass $data)
    {
        $data->{'$schema'} = sprintf(ModuleFileConverter::SCHEMA, '2.0');

        unset($data->version);

        if (isset($data->packages)) {
            $data->modules = $data->packages;
            unset($data->packages);
        }

        if (isset($data->{'path-mappings'})) {
            $data->resources = $data->{'path-mappings'};
            unset($data->{'path-mappings'});
        }

        if (isset($data->override) && !is_array($data->override)) {
            $data->override = array($data->override);
        }

        if (isset($data->{'override-order'})) {
            $data->order = $data->{'override-order'};
            unset($data->{'override-order'});
        }

        if (isset($data->override)) {
            $data->depend = $data->override;
            unset($data->override);
        }
    }

    public function down(stdClass $data)
    {
        unset($data->{'$schema'});

        $data->version = '1.0';

        if (isset($data->modules)) {
            $data->packages = $data->modules;
            unset($data->modules);
        }

        if (isset($data->resources)) {
            $data->{'path-mappings'} = $data->resources;
            unset($data->resources);
        }

        if (isset($data->override) && 1 === count($data->override)) {
            $data->override = reset($data->override);
        }

        if (isset($data->order)) {
            $data->{'override-order'} = $data->order;
            unset($data->order);
        }

        if (isset($data->depend)) {
            $data->override = $data->depend;
            unset($data->depend);
        }
    }
}
