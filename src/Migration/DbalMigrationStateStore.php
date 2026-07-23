<?php declare(strict_types=1);

namespace Frosh\Jetpack\Migration;

use Doctrine\DBAL\Connection;

/**
 * @internal
 */
final class DbalMigrationStateStore implements MigrationStateStore
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function initialize(): void
    {
        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `frosh_jetpack_migration` (
                `bundle_name` VARBINARY(255) NOT NULL,
                `migration_class` VARBINARY(512) NOT NULL,
                `creation_timestamp` BIGINT UNSIGNED NOT NULL,
                `executed_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (`bundle_name`, `migration_class`),
                UNIQUE KEY `uniq.frosh_jetpack_migration.timestamp` (`bundle_name`, `creation_timestamp`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function executed(string $bundle): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT `migration_class`, `creation_timestamp`
                FROM `frosh_jetpack_migration`
                WHERE `bundle_name` = :bundle
                ORDER BY `creation_timestamp` ASC
                SQL,
            ['bundle' => $bundle],
        );

        return array_map(static function (array $row): ExecutedMigration {
            $class = $row['migration_class'] ?? null;
            $timestamp = $row['creation_timestamp'] ?? null;
            if (!\is_string($class) || (!\is_int($timestamp) && !\is_string($timestamp))) {
                throw new MigrationException('The Jetpack migration state contains an invalid row.');
            }

            /** @var class-string<Migration> $class */
            return new ExecutedMigration($class, (int) $timestamp);
        }, $rows);
    }

    public function markExecuted(string $bundle, string $class, int $creationTimestamp): void
    {
        $this->connection->insert('frosh_jetpack_migration', [
            'bundle_name' => $bundle,
            'migration_class' => $class,
            'creation_timestamp' => $creationTimestamp,
        ]);
    }

    public function remove(string $bundle, string $class): void
    {
        $this->connection->delete('frosh_jetpack_migration', [
            'bundle_name' => $bundle,
            'migration_class' => $class,
        ]);
    }
}
