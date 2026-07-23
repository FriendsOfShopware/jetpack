<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Configuration;

use Doctrine\DBAL\Connection;
use Frosh\Jetpack\Configuration\ConfigurationAddress;
use Frosh\Jetpack\Configuration\DbalConfigurationStore;
use Frosh\Jetpack\Configuration\StoredConfigurationValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(DbalConfigurationStore::class)]
final class DbalConfigurationStoreTest extends TestCase
{
    private const SALES_CHANNEL = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const LANGUAGE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testLoadsJsonValuesAndBinaryScopeIds(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            [
                'configuration_key' => 'enabled',
                'configuration_value' => 'false',
                'sales_channel_id' => self::SALES_CHANNEL,
                'language_id' => self::LANGUAGE,
            ],
        ]);

        $values = (new DbalConfigurationStore($connection, static::createStub(ClockInterface::class)))
            ->load('TestBundle');

        static::assertCount(1, $values);
        static::assertSame('enabled', $values[0]->address->key);
        static::assertSame(self::SALES_CHANNEL, $values[0]->address->salesChannelId);
        static::assertSame(self::LANGUAGE, $values[0]->address->languageId);
        static::assertFalse($values[0]->value);
    }

    public function testAppliesWritesAndDeletesInOneTransaction(): void
    {
        $statements = [];
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static function (callable $transaction): void {
                $transaction();
            });
        $connection->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $parameters) use (&$statements): int {
                $statements[] = [$sql, $parameters];

                return 1;
            });
        $clock = static::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-07-22 12:00:00'));
        $store = new DbalConfigurationStore($connection, $clock);

        $store->apply('TestBundle', [
            new StoredConfigurationValue(
                new ConfigurationAddress('enabled', self::SALES_CHANNEL, self::LANGUAGE),
                true,
            ),
        ], [new ConfigurationAddress('headline', null, null)]);

        static::assertStringContainsString('INSERT INTO frosh_jetpack_configuration', $statements[0][0]);
        static::assertSame('true', $statements[0][1]['configurationValue']);
        static::assertSame(Uuid::fromHexToBytes(self::SALES_CHANNEL), $statements[0][1]['salesChannelId']);
        static::assertSame('2026-07-22 12:00:00.000', $statements[0][1]['createdAt']);
        static::assertStringContainsString('DELETE FROM frosh_jetpack_configuration', $statements[1][0]);
        static::assertSame('headline', $statements[1][1]['configurationKey']);
    }
}
