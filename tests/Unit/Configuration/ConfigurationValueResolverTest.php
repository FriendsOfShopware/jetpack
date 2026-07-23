<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Configuration;

use Frosh\Jetpack\Configuration\ConfigurationAddress;
use Frosh\Jetpack\Configuration\ConfigurationScope;
use Frosh\Jetpack\Configuration\ConfigurationValueResolver;
use Frosh\Jetpack\Configuration\FieldDefinition;
use Frosh\Jetpack\Configuration\StoredConfigurationValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigurationValueResolver::class)]
final class ConfigurationValueResolverTest extends TestCase
{
    private const SALES_CHANNEL = 'sales-channel';
    private const LANGUAGE = 'language';
    private const PARENT_LANGUAGE = 'parent-language';

    #[DataProvider('fallbackProvider')]
    public function testResolvesSalesChannelBeforeLanguageFallback(int $firstStoredCandidate, string $expected): void
    {
        $storedValues = array_map(
            static fn (array $candidate): StoredConfigurationValue => new StoredConfigurationValue(
                new ConfigurationAddress('headline', $candidate[0], $candidate[1]),
                $candidate[2],
            ),
            \array_slice(self::candidates(), $firstStoredCandidate),
        );

        $resolved = (new ConfigurationValueResolver())->resolve(
            $this->field(ConfigurationScope::SalesChannelLanguage),
            $storedValues,
            [self::LANGUAGE, self::PARENT_LANGUAGE],
            self::SALES_CHANNEL,
        );

        static::assertSame($expected, $resolved->value);
    }

    public static function fallbackProvider(): \Generator
    {
        yield 'exact sales channel and language' => [0, 'sales-channel-language'];
        yield 'sales channel and parent language' => [1, 'sales-channel-parent-language'];
        yield 'sales channel and language independent' => [2, 'sales-channel'];
        yield 'global and current language' => [3, 'language'];
        yield 'global and parent language' => [4, 'parent-language'];
        yield 'global and language independent' => [5, 'global'];
        yield 'YAML default' => [6, 'yaml-default'];
    }

    public function testGlobalScopeIgnoresScopedValues(): void
    {
        $resolved = (new ConfigurationValueResolver())->resolve(
            $this->field(ConfigurationScope::Global),
            [
                new StoredConfigurationValue(
                    new ConfigurationAddress('headline', self::SALES_CHANNEL, self::LANGUAGE),
                    'scoped',
                ),
                new StoredConfigurationValue(new ConfigurationAddress('headline', null, null), 'global'),
            ],
            [self::LANGUAGE],
            self::SALES_CHANNEL,
        );

        static::assertSame('global', $resolved->value);
        static::assertFalse($resolved->inherited);
    }

    /**
     * @return list<array{0: ?string, 1: ?string, 2: string}>
     */
    private static function candidates(): array
    {
        return [
            [self::SALES_CHANNEL, self::LANGUAGE, 'sales-channel-language'],
            [self::SALES_CHANNEL, self::PARENT_LANGUAGE, 'sales-channel-parent-language'],
            [self::SALES_CHANNEL, null, 'sales-channel'],
            [null, self::LANGUAGE, 'language'],
            [null, self::PARENT_LANGUAGE, 'parent-language'],
            [null, null, 'global'],
        ];
    }

    private function field(ConfigurationScope $scope): FieldDefinition
    {
        return new FieldDefinition('headline', 'text', $scope, 'yaml-default', []);
    }
}
