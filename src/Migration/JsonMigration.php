<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Migration;

use stdClass;

/**
 * Migrates a JSON object between versions.
 *
 * The JSON object is expected to have the property "version" set.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface JsonMigration
{
    /**
     * Returns the version of the JSON object that this migration expects.
     *
     * @return string The version string.
     */
    public function getOriginVersion();

    /**
     * Returns the version of the JSON object that this migration upgrades to.
     *
     * @return string The version string.
     */
    public function getTargetVersion();

    /**
     * Upgrades the given JSON object from the origin to the target version.
     *
     * @param stdClass $data The JSON object of the package file.
     */
    public function up(stdClass $data);

    /**
     * Reverts the given JSON object from the target to the origin version.
     *
     * @param stdClass $data The JSON object of the package file.
     */
    public function down(stdClass $data);
}
