<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Configuration;

use Frosh\Jetpack\Configuration\BundleReference;
use Frosh\Jetpack\Configuration\ConfigurationException;
use Frosh\Jetpack\Configuration\ConfigurationScope;
use Frosh\Jetpack\Configuration\ValueTypeValidator;
use Frosh\Jetpack\Configuration\YamlConfigurationLoader;
use Frosh\Jetpack\Tests\Fixture\TestBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlConfigurationLoader::class)]
final class YamlConfigurationLoaderTest extends TestCase
{
    public function testLoadsAndNormalizesAValidDefinition(): void
    {
        $definition = $this->loader()->load(new BundleReference(
            TestBundle::class,
            'TestBundle',
            (new TestBundle())->getPath(),
        ));

        static::assertCount(6, $definition->fields);
        static::assertSame(ConfigurationScope::SalesChannelLanguage, $definition->field('enabled')->scope);
        static::assertSame(ConfigurationScope::Global, $definition->field('retries')->scope);
        static::assertSame(0, $definition->document['tabs']['general']['position']);
        static::assertFalse($definition->document['tabs']['general']['sections']['behavior']['fields']['enabled']['nullable']);
    }

    #[DataProvider('invalidDefinitionProvider')]
    public function testRejectsInvalidDefinitions(string $fixture): void
    {
        $this->expectException(ConfigurationException::class);

        $this->loader()->load(new BundleReference(
            TestBundle::class,
            $fixture,
            \dirname(__DIR__, 2) . '/Fixture/' . $fixture,
        ));
    }

    public static function invalidDefinitionProvider(): \Generator
    {
        yield 'duplicate field key' => ['InvalidDuplicateBundle'];
        yield 'default outside constraints' => ['InvalidDefaultBundle'];
        yield 'select without options' => ['MissingOptionsBundle'];
    }

    private function loader(): YamlConfigurationLoader
    {
        return new YamlConfigurationLoader(new ValueTypeValidator());
    }
}
