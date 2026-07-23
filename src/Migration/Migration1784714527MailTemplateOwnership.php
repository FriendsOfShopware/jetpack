<?php declare(strict_types=1);

namespace Frosh\Jetpack\Migration;

use Doctrine\DBAL\Connection;

final class Migration1784714527MailTemplateOwnership extends Migration
{
    public function getCreationTimestamp(): int
    {
        return 1784714527;
    }

    public function up(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `frosh_jetpack_mail_template` (
                `id` BINARY(16) NOT NULL,
                `bundle_name` VARBINARY(255) NOT NULL,
                `technical_name` VARBINARY(255) NOT NULL,
                `mail_template_type_id` BINARY(16) NOT NULL,
                `mail_template_id` BINARY(16) NOT NULL,
                `available_entities_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.frosh_jetpack_mail_template.bundle_name_technical_name` (`bundle_name`, `technical_name`),
                UNIQUE KEY `uniq.frosh_jetpack_mail_template.type_id` (`mail_template_type_id`),
                UNIQUE KEY `uniq.frosh_jetpack_mail_template.template_id` (`mail_template_id`),
                CONSTRAINT `fk.frosh_jetpack_mail_template.type_id`
                    FOREIGN KEY (`mail_template_type_id`) REFERENCES `mail_template_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.frosh_jetpack_mail_template.template_id`
                    FOREIGN KEY (`mail_template_id`) REFERENCES `mail_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `frosh_jetpack_mail_template_translation` (
                `mail_template_ownership_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `type_name_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `content_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                PRIMARY KEY (`mail_template_ownership_id`, `language_id`),
                CONSTRAINT `fk.frosh_jetpack_mail_template_translation.ownership_id`
                    FOREIGN KEY (`mail_template_ownership_id`) REFERENCES `frosh_jetpack_mail_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.frosh_jetpack_mail_template_translation.language_id`
                    FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `frosh_jetpack_mail_template_translation`');
        $connection->executeStatement('DROP TABLE IF EXISTS `frosh_jetpack_mail_template`');
    }
}
