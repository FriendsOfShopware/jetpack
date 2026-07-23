<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Configuration;

use Frosh\Jetpack\Attribute\ConfigKey;
use Frosh\Jetpack\Attribute\JetpackConfig;
use Frosh\Jetpack\Configuration\BundleConfigurationLocator;
use Frosh\Jetpack\Configuration\ConfigurationAddress;
use Frosh\Jetpack\Configuration\ConfigurationDefinitionRegistry;
use Frosh\Jetpack\Configuration\ConfigurationValueResolver;
use Frosh\Jetpack\Configuration\DefaultConfigurationService;
use Frosh\Jetpack\Configuration\DtoHydrator;
use Frosh\Jetpack\Configuration\StoredConfigurationValue;
use Frosh\Jetpack\Configuration\ValueTypeValidator;
use Frosh\Jetpack\Configuration\YamlConfigurationLoader;
use Frosh\Jetpack\Tests\Fixture\InMemoryConfigurationStore;
use Frosh\Jetpack\Tests\Fixture\TestBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(DefaultConfigurationService::class)]
final class DefaultConfigurationServiceTest extends TestCase
{
    private const SALES_CHANNEL = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const LANGUAGE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const PARENT_LANGUAGE = 'cccccccccccccccccccccccccccccccc';

    public function testLoadsImmutableDtoAndScalarValuesWithARegularContext(): void
    {
        $store = new InMemoryConfigurationStore();
        $store->values = [
            new StoredConfigurationValue(
                new ConfigurationAddress('enabled', self::SALES_CHANNEL, self::LANGUAGE),
                false,
            ),
            new StoredConfigurationValue(new ConfigurationAddress('headline', null, self::LANGUAGE), 'Localized'),
            new StoredConfigurationValue(new ConfigurationAddress('mode', null, null), 'fast'),
            new StoredConfigurationValue(
                new ConfigurationAddress('features', self::SALES_CHANNEL, null),
                ['export'],
            ),
        ];
        $service = $this->service($store);

        $config = $service->load(TestConfiguration::class, $this->context(), self::SALES_CHANNEL);

        static::assertFalse($config->enabled);
        static::assertSame('Localized', $config->title);
        static::assertSame(3, $config->retries);
        static::assertSame(1.5, $config->ratio);
        static::assertSame(TestMode::Fast, $config->mode);
        static::assertSame(['export'], $config->features);
        static::assertFalse($service->getBool(TestBundle::class, 'enabled', $this->context(), self::SALES_CHANNEL));
        static::assertSame(1, $store->loadCalls, 'All keys of one bundle share the request-local read.');

        $service->reset();
        $service->getBool(TestBundle::class, 'enabled', $this->context(), self::SALES_CHANNEL);
        static::assertSame(2, $store->loadCalls);
    }

    private function service(InMemoryConfigurationStore $store): DefaultConfigurationService
    {
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundles')->willReturn([new TestBundle()]);
        $validator = new ValueTypeValidator();

        return new DefaultConfigurationService(
            new ConfigurationDefinitionRegistry(
                new BundleConfigurationLocator($kernel),
                new YamlConfigurationLoader($validator),
            ),
            $store,
            new ConfigurationValueResolver(),
            new DtoHydrator(),
        );
    }

    private function context(): Context
    {
        return new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [self::LANGUAGE, self::PARENT_LANGUAGE],
        );
    }
}

enum TestMode: string
{
    case Safe = 'safe';
    case Fast = 'fast';
}

#[JetpackConfig(TestBundle::class)]
final readonly class TestConfiguration
{
    /**
     * @param list<string> $features
     */
    public function __construct(
        public bool $enabled,
        #[ConfigKey('headline')]
        public string $title,
        public int $retries,
        public float $ratio,
        public TestMode $mode,
        public array $features,
    ) {
    }
}
