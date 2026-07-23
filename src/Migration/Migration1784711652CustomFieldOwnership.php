<?php declare(strict_types=1);

namespace Frosh\Jetpack\Migration;

use Doctrine\DBAL\Connection;

final class Migration1784711652CustomFieldOwnership extends Migration
{
    public function getCreationTimestamp(): int
    {
        return 1784711652;
    }

    public function up(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `frosh_jetpack_custom_field_set` (
                `bundle_name` VARBINARY(255) NOT NULL,
                `set_name` VARBINARY(255) NOT NULL,
                `set_id` BINARY(16) NOT NULL,
                PRIMARY KEY (`bundle_name`, `set_name`),
                UNIQUE KEY `uniq.frosh_jetpack_custom_field_set.set_id` (`set_id`),
                CONSTRAINT `fk.frosh_jetpack_custom_field_set.set_id`
                    FOREIGN KEY (`set_id`) REFERENCES `custom_field_set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `frosh_jetpack_custom_field_set`');
    }
}
