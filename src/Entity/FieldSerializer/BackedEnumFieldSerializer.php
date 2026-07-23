<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\FieldSerializer;

use Doctrine\DBAL\Types\Types;
use Frosh\Jetpack\Entity\Field\BackedEnumField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\IsNull;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

final class BackedEnumFieldSerializer extends AbstractFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters,
    ): \Generator {
        $enumField = $this->field($field);
        $enumClass = $enumField->getEnumClass();
        $value = $data->getValue();
        if ($value instanceof \BackedEnum) {
            if (!$value instanceof $enumClass) {
                throw new \InvalidArgumentException(\sprintf('Field "%s" expects an instance of %s.', $field->getPropertyName(), $enumClass));
            }
            $value = $value->value;
            $data->setValue($value);
        }
        $this->validateIfNeeded($enumField, $existence, $data, $parameters);

        $enum = $value === null ? null : $enumClass::tryFrom($value);
        if ($value !== null && $enum === null) {
            throw new \InvalidArgumentException(\sprintf('Value for field "%s" is not a valid %s case.', $field->getPropertyName(), $enumClass));
        }
        $data->setValue($enum?->value);
        $this->validateIfNeeded($enumField, $existence, $data, $parameters);

        yield $enumField->getStorageName() => $enum?->value;
    }

    public function decode(Field $field, mixed $value): ?\BackedEnum
    {
        $enumField = $this->field($field);
        if ($value === null) {
            return null;
        }

        if ($enumField->getType() === Types::INTEGER) {
            $value = is_numeric($value)
                ? (int) $value
                : throw new \InvalidArgumentException(\sprintf('Field "%s" expects an integer-backed enum value.', $field->getPropertyName()));
        } else {
            $value = (string) $value;
        }

        $enum = $enumField->getEnumClass()::tryFrom($value);
        if ($enum === null) {
            throw new \UnexpectedValueException(\sprintf('Stored value for field "%s" is not a valid %s case.', $field->getPropertyName(), $enumField->getEnumClass()));
        }

        return $enum;
    }

    /**
     * @return list<Constraint>
     */
    protected function getConstraints(Field $field): array
    {
        $enumField = $this->field($field);
        $constraints = [new AtLeastOneOf([
            new Type($enumField->getType()),
            new IsNull(),
        ])];
        if ($field->is(Required::class)) {
            $constraints[] = new NotBlank();
        }

        return $constraints;
    }

    private function field(Field $field): BackedEnumField
    {
        if (!$field instanceof BackedEnumField) {
            throw new \InvalidArgumentException(\sprintf('%s only supports %s.', self::class, BackedEnumField::class));
        }

        return $field;
    }
}
