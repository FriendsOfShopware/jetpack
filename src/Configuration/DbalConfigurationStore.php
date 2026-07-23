<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
final class DbalConfigurationStore implements ConfigurationStore
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function load(string $bundleName): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    configuration_key,
                    configuration_value,
                    LOWER(HEX(sales_channel_id)) AS sales_channel_id,
                    LOWER(HEX(language_id)) AS language_id
                FROM frosh_jetpack_configuration
                WHERE bundle_name = :bundleName
                SQL,
            ['bundleName' => $bundleName],
        );

        return array_map(static function (array $row): StoredConfigurationValue {
            return new StoredConfigurationValue(
                new ConfigurationAddress(
                    (string) $row['configuration_key'],
                    $row['sales_channel_id'] !== null ? (string) $row['sales_channel_id'] : null,
                    $row['language_id'] !== null ? (string) $row['language_id'] : null,
                ),
                json_decode((string) $row['configuration_value'], true, 512, \JSON_THROW_ON_ERROR),
            );
        }, $rows);
    }

    public function apply(string $bundleName, array $writes, array $deletes): void
    {
        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->transactional(function () use ($bundleName, $writes, $deletes, $now): void {
            foreach ($writes as $write) {
                $this->connection->executeStatement(
                    <<<'SQL'
                        INSERT INTO frosh_jetpack_configuration (
                            id,
                            bundle_name,
                            configuration_key,
                            sales_channel_id,
                            language_id,
                            scope_key,
                            configuration_value,
                            created_at
                        ) VALUES (
                            :id,
                            :bundleName,
                            :configurationKey,
                            :salesChannelId,
                            :languageId,
                            :scopeKey,
                            :configurationValue,
                            :createdAt
                        )
                        ON DUPLICATE KEY UPDATE
                            configuration_value = VALUES(configuration_value),
                            updated_at = VALUES(created_at)
                        SQL,
                    [
                        'id' => Uuid::randomBytes(),
                        'bundleName' => $bundleName,
                        'configurationKey' => $write->address->key,
                        'salesChannelId' => $write->address->salesChannelId !== null
                            ? Uuid::fromHexToBytes($write->address->salesChannelId)
                            : null,
                        'languageId' => $write->address->languageId !== null
                            ? Uuid::fromHexToBytes($write->address->languageId)
                            : null,
                        'scopeKey' => $write->address->scopeKey(),
                        'configurationValue' => json_encode($write->value, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR),
                        'createdAt' => $now,
                    ],
                );
            }

            foreach ($deletes as $delete) {
                $this->connection->executeStatement(
                    <<<'SQL'
                        DELETE FROM frosh_jetpack_configuration
                        WHERE bundle_name = :bundleName
                          AND configuration_key = :configurationKey
                          AND scope_key = :scopeKey
                        SQL,
                    [
                        'bundleName' => $bundleName,
                        'configurationKey' => $delete->key,
                        'scopeKey' => $delete->scopeKey(),
                    ],
                );
            }
        });
    }

    public function deleteBundle(string $bundleName): void
    {
        $this->connection->delete('frosh_jetpack_configuration', ['bundle_name' => $bundleName]);
    }
}
