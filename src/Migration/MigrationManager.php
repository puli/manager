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

use Puli\Manager\Api\Migration\MigrationException;
use Puli\Manager\Assert\Assert;
use stdClass;

/**
 * Migrates JSON objects between versions.
 *
 * The JSON object is expected to have the property "version" set.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MigrationManager
{
    /**
     * @var JsonMigration[]
     */
    private $migrationsByOriginVersion = array();

    /**
     * @var JsonMigration[]
     */
    private $migrationsByTargetVersion = array();

    /**
     * Creates a new migration manager.
     *
     * @param JsonMigration[] $migrations
     */
    public function __construct(array $migrations)
    {
        Assert::allIsInstanceOf($migrations, __NAMESPACE__.'\JsonMigration');

        foreach ($migrations as $migration) {
            $this->migrationsByOriginVersion[$migration->getOriginVersion()] = $migration;
            $this->migrationsByTargetVersion[$migration->getTargetVersion()] = $migration;
        }

        uksort($this->migrationsByOriginVersion, 'version_compare');
        uksort($this->migrationsByTargetVersion, 'version_compare');
    }

    /**
     * Migrates a JSON object to the given target version.
     *
     * @param stdClass $data          The JSON object.
     * @param string   $targetVersion The version string.
     *
     * @throws MigrationException If the file cannot be migrated.
     */
    public function migrate(stdClass $data, $targetVersion)
    {
        if (version_compare($targetVersion, $data->version, '>')) {
            $this->up($data, $targetVersion);
        } elseif (version_compare($targetVersion, $data->version, '<')) {
            $this->down($data, $targetVersion);
        }
    }

    /**
     * Returns all versions known to the manager.
     *
     * @return string[] The known version strings.
     */
    public function getKnownVersions()
    {
        $versions = array_unique(array_merge(
            array_keys($this->migrationsByOriginVersion),
            array_keys($this->migrationsByTargetVersion)
        ));

        usort($versions, 'version_compare');

        return $versions;
    }

    protected function up(stdClass $data, $targetVersion)
    {
        while (version_compare($data->version, $targetVersion, '<')) {
            // No migration for origin version
            if (!isset($this->migrationsByOriginVersion[$data->version])) {
                throw new MigrationException(sprintf(
                    'No migration found to upgrade from version %s to %s.',
                    $data->version,
                    $targetVersion
                ));
            }

            $migration = $this->migrationsByOriginVersion[$data->version];

            // Final version too high
            if (version_compare($migration->getTargetVersion(), $targetVersion, '>')) {
                throw new MigrationException(sprintf(
                    'No migration found to upgrade from version %s to %s.',
                    $data->version,
                    $targetVersion
                ));
            }

            $migration->up($data);

            $data->version = $migration->getTargetVersion();
        }
    }

    protected function down(stdClass $data, $targetVersion)
    {
        while (version_compare($data->version, $targetVersion, '>')) {
            // No migration for origin version
            if (!isset($this->migrationsByTargetVersion[$data->version])) {
                throw new MigrationException(sprintf(
                    'No migration found to downgrade from version %s to %s.',
                    $data->version,
                    $targetVersion
                ));
            }

            $migration = $this->migrationsByTargetVersion[$data->version];

            // Final version too low
            if (version_compare($migration->getOriginVersion(), $targetVersion, '<')) {
                throw new MigrationException(sprintf(
                    'No migration found to downgrade from version %s to %s.',
                    $data->version,
                    $targetVersion
                ));
            }

            $migration->down($data);

            $data->version = $migration->getOriginVersion();
        }
    }
}
