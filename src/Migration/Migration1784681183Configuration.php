<?php declare(strict_types=1);

namespace Frosh\Jetpack\Migration;

use Doctrine\DBAL\Connection;

final class Migration1784681183Configuration extends Migration
{
    public function getCreationTimestamp(): int
    {
        return 1784681183;
    }

    public function up(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `frosh_jetpack_configuration` (
                `id` BINARY(16) NOT NULL,
                `bundle_name` VARCHAR(255) COLLATE utf8mb4_bin NOT NULL,
                `configuration_key` VARCHAR(255) COLLATE utf8mb4_bin NOT NULL,
                `sales_channel_id` BINARY(16) NULL,
                `language_id` BINARY(16) NULL,
                `scope_key` BINARY(32) NOT NULL,
                `configuration_value` JSON NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.frosh_jetpack_configuration.address` (`bundle_name`, `configuration_key`, `scope_key`),
                KEY `idx.frosh_jetpack_configuration.sales_channel_id` (`sales_channel_id`),
                KEY `idx.frosh_jetpack_configuration.language_id` (`language_id`),
                CONSTRAINT `json.frosh_jetpack_configuration.configuration_value` CHECK (JSON_VALID(`configuration_value`)),
                CONSTRAINT `fk.frosh_jetpack_configuration.sales_channel_id`
                    FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.frosh_jetpack_configuration.language_id`
                    FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL);
    }

    public function down(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `frosh_jetpack_configuration`');
    }
}
