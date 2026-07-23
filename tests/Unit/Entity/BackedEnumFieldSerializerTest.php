<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Entity;

use Doctrine\DBAL\Types\Types;
use Frosh\Jetpack\Entity\Field\BackedEnumField;
use Frosh\Jetpack\Entity\FieldSerializer\BackedEnumFieldSerializer;
use Frosh\Jetpack\Tests\Fixture\Entity\ExampleMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(BackedEnumField::class)]
#[CoversClass(BackedEnumFieldSerializer::class)]
final class BackedEnumFieldSerializerTest extends TestCase
{
    public function testDecodesDatabaseValueToTypedEnum(): void
    {
        $field = new BackedEnumField('mode', 'mode', ExampleMode::class);
        $registry = new DefinitionInstanceRegistry(static::createStub(ContainerInterface::class), [], []);
        $serializer = new BackedEnumFieldSerializer(static::createStub(ValidatorInterface::class), $registry);

        static::assertSame(Types::STRING, $field->getType());
        static::assertSame(ExampleMode::Enabled, $serializer->decode($field, ExampleMode::Enabled->value));
        static::assertNull($serializer->decode($field, null));
    }

    public function testRejectsUnknownStoredEnumValue(): void
    {
        $field = new BackedEnumField('mode', 'mode', ExampleMode::class);
        $registry = new DefinitionInstanceRegistry(static::createStub(ContainerInterface::class), [], []);
        $serializer = new BackedEnumFieldSerializer(static::createStub(ValidatorInterface::class), $registry);

        $this->expectException(\UnexpectedValueException::class);

        $serializer->decode($field, 'unknown');
    }
}
