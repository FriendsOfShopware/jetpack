<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Configuration;

use Frosh\Jetpack\Configuration\BundleConfigurationLocator;
use Frosh\Jetpack\Configuration\ConfigurationAddress;
use Frosh\Jetpack\Configuration\ConfigurationDefinitionRegistry;
use Frosh\Jetpack\Configuration\ConfigurationException;
use Frosh\Jetpack\Configuration\ConfigurationValueResolver;
use Frosh\Jetpack\Configuration\ConfigurationWriteService;
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

#[CoversClass(ConfigurationWriteService::class)]
final class ConfigurationWriteServiceTest extends TestCase
{
    private const SALES_CHANNEL = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const LANGUAGE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testWritesScopedValueAndInvalidatesReadMemoization(): void
    {
        [$writer, $configuration, $store] = $this->services();
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [self::LANGUAGE]);

        static::assertTrue($configuration->getBool(TestBundle::class, 'enabled', $context, self::SALES_CHANNEL));

        $writer->apply(TestBundle::class, [
            new StoredConfigurationValue(
                new ConfigurationAddress('enabled', self::SALES_CHANNEL, self::LANGUAGE),
                false,
            ),
        ], []);

        static::assertFalse($configuration->getBool(TestBundle::class, 'enabled', $context, self::SALES_CHANNEL));
        static::assertSame(2, $store->loadCalls);
    }

    public function testRejectsDimensionsNotSupportedByFieldScope(): void
    {
        [$writer] = $this->services();

        $this->expectException(ConfigurationException::class);
        $writer->apply(TestBundle::class, [
            new StoredConfigurationValue(
                new ConfigurationAddress('retries', self::SALES_CHANNEL, null),
                4,
            ),
        ], []);
    }

    public function testRejectsValueOutsideYamlConstraints(): void
    {
        [$writer] = $this->services();

        $this->expectException(ConfigurationException::class);
        $writer->apply(TestBundle::class, [
            new StoredConfigurationValue(new ConfigurationAddress('retries', null, null), 11),
        ], []);
    }

    /**
     * @return array{ConfigurationWriteService, DefaultConfigurationService, InMemoryConfigurationStore}
     */
    private function services(): array
    {
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundles')->willReturn([new TestBundle()]);
        $validator = new ValueTypeValidator();
        $definitions = new ConfigurationDefinitionRegistry(
            new BundleConfigurationLocator($kernel),
            new YamlConfigurationLoader($validator),
        );
        $store = new InMemoryConfigurationStore();
        $configuration = new DefaultConfigurationService(
            $definitions,
            $store,
            new ConfigurationValueResolver(),
            new DtoHydrator(),
        );

        return [
            new ConfigurationWriteService($definitions, $store, $validator, $configuration),
            $configuration,
            $store,
        ];
    }
}
