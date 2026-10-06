<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Creates the optional administrative-division tree tables.
 *
 * Additive only — new tables, no change to core tables. `parent_id` self-refers
 * for arbitrary depth; `country_state_id` binds every division to a core state.
 * The `code` is globally unique so the reference importer can upsert by it.
 * Tables carry the `kmh_af_` prefix to stay clear of the global DAL namespace.
 */
class Migration1786500000CreateAdministrativeDivisionTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786500000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `kmh_af_administrative_division` (
                `id`               BINARY(16)  NOT NULL,
                `country_state_id` BINARY(16)  NOT NULL,
                `parent_id`        BINARY(16)  NULL,
                `code`             VARCHAR(64) NOT NULL,
                `level`            INT         NOT NULL DEFAULT 1,
                `type`             VARCHAR(64) NULL,
                `active`           TINYINT(1)  NOT NULL DEFAULT 1,
                `custom_fields`    JSON        NULL,
                `created_at`       DATETIME(3) NOT NULL,
                `updated_at`       DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.kmh_af_administrative_division.code` (`code`),
                KEY `fk.kmh_af_administrative_division.country_state_id` (`country_state_id`),
                KEY `fk.kmh_af_administrative_division.parent_id` (`parent_id`),
                CONSTRAINT `fk.kmh_af_administrative_division.country_state_id`
                    FOREIGN KEY (`country_state_id`) REFERENCES `country_state` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.kmh_af_administrative_division.parent_id`
                    FOREIGN KEY (`parent_id`) REFERENCES `kmh_af_administrative_division` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `kmh_af_administrative_division_translation` (
                `kmh_af_administrative_division_id` BINARY(16)   NOT NULL,
                `language_id`                       BINARY(16)   NOT NULL,
                `name`                              VARCHAR(255) NOT NULL,
                `created_at`                        DATETIME(3)  NOT NULL,
                `updated_at`                        DATETIME(3)  NULL,
                PRIMARY KEY (`kmh_af_administrative_division_id`, `language_id`),
                KEY `fk.kmh_af_admin_division_translation.language_id` (`language_id`),
                CONSTRAINT `fk.kmh_af_admin_division_translation.division_id`
                    FOREIGN KEY (`kmh_af_administrative_division_id`) REFERENCES `kmh_af_administrative_division` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.kmh_af_admin_division_translation.language_id`
                    FOREIGN KEY (`language_id`) REFERENCES `language` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Intentionally empty — the plugin's uninstall drops the tables when data is not kept.
    }
}
