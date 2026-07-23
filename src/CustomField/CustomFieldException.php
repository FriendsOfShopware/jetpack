<?php declare(strict_types=1);

namespace Frosh\Jetpack\CustomField;

final class CustomFieldException extends \RuntimeException
{
    public static function invalidDefinition(string $path, string $reason): self
    {
        return new self(\sprintf('Invalid Jetpack custom field definition "%s": %s', $path, $reason));
    }

    public static function immutableFieldType(string $field, string $existing, string $declared): self
    {
        return new self(\sprintf(
            'Custom field "%s" already uses immutable storage type "%s" and cannot be changed to "%s". Introduce a new field name and migrate its values instead.',
            $field,
            $existing,
            $declared,
        ));
    }
}
