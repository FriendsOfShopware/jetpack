<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Configuration;

use Frosh\Jetpack\Attribute\JetpackConfig;
use Frosh\Jetpack\Configuration\ConfigurationException;
use Frosh\Jetpack\Configuration\DtoHydrator;
use Frosh\Jetpack\Tests\Fixture\TestBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DtoHydrator::class)]
final class DtoHydratorTest extends TestCase
{
    public function testRejectsDtoWithoutAttribute(): void
    {
        $this->expectException(ConfigurationException::class);

        (new DtoHydrator())->bundle(DtoWithoutAttribute::class);
    }

    public function testWrapsInvalidBackedEnumValue(): void
    {
        $this->expectException(ConfigurationException::class);

        (new DtoHydrator())->hydrate(
            DtoWithEnum::class,
            static fn (string $key): string => 'invalid',
        );
    }
}

final readonly class DtoWithoutAttribute
{
    public function __construct(public string $value)
    {
    }
}

#[JetpackConfig(TestBundle::class)]
final readonly class DtoWithEnum
{
    public function __construct(public TestEnum $value)
    {
    }
}

enum TestEnum: string
{
    case Valid = 'valid';
}
