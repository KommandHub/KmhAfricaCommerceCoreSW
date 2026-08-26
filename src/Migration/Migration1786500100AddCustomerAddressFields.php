<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Adds the additive African address data as a 1:1 aggregate of customer_address.
 *
 * Shopware forbids an EntityExtension from adding plain storage columns to a core
 * entity, so the data lives in its own `kmh_af_customer_address_data` table and
 * attaches through a OneToOne association. Additive only; `updateDestructive` is
 * empty so uninstall keeps the data. Runs after the division tables — the
 * aggregate's division FK references them.
 */
class Migration1786500100AddCustomerAddressFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786500100;
    }

    public function update(Connection $connection): void
    {
        $this->dropLegacyColumns($connection);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `kmh_af_customer_address_data` (
                `id`                   BINARY(16)   NOT NULL,
                `customer_address_id`  BINARY(16)   NOT NULL,
                `landmark`             VARCHAR(255) NULL,
                `area`                 VARCHAR(255) NULL,
                `directions`           LONGTEXT     NULL,
                `digital_address_code` VARCHAR(255) NULL,
                `division_id`          BINARY(16)   NULL,
                `custom_fields`        JSON         NULL,
                `created_at`           DATETIME(3)  NOT NULL,
                `updated_at`           DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.kmh_af_customer_address_data.address` (`customer_address_id`),
                KEY `fk.kmh_af_customer_address_data.division_id` (`division_id`),
                CONSTRAINT `fk.kmh_af_customer_address_data.address_id`
                    FOREIGN KEY (`customer_address_id`) REFERENCES `customer_address` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.kmh_af_customer_address_data.division_id`
                    FOREIGN KEY (`division_id`) REFERENCES `kmh_af_administrative_division` (`id`)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Intentionally empty — keep customer address data on uninstall.
    }

    /**
     * Remove the invalid direct columns an earlier version of this migration
     * added to customer_address (extensions may not add storage columns).
     */
    private function dropLegacyColumns(Connection $connection): void
    {
        $columns = $connection->createSchemaManager()->listTableColumns('customer_address');

        if (!\array_key_exists('kmh_af_landmark', $columns)) {
            return;
        }

        $connection->executeStatement(<<<'SQL'
            ALTER TABLE `customer_address`
                DROP FOREIGN KEY `fk.customer_address.kmh_af_division_id`;
        SQL);

        $connection->executeStatement(<<<'SQL'
            ALTER TABLE `customer_address`
                DROP COLUMN `kmh_af_landmark`,
                DROP COLUMN `kmh_af_area`,
                DROP COLUMN `kmh_af_directions`,
                DROP COLUMN `kmh_af_digital_address_code`,
                DROP COLUMN `kmh_af_division_id`;
        SQL);
    }
}
