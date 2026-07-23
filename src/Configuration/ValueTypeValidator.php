<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

/**
 * @internal
 */
final class ValueTypeValidator
{
    public function validate(FieldDefinition $field, mixed $value): void
    {
        $required = ($field->config['required'] ?? false) === true;
        if ($value === null && ($field->config['nullable'] ?? false) === true && !$required) {
            return;
        }

        if ($required && ($value === null || $value === '' || $value === [])) {
            throw ConfigurationException::invalidValue($field->key, 'a non-empty value', $value);
        }

        $valid = match ($field->type) {
            'bool' => \is_bool($value),
            'int' => \is_int($value),
            'float' => \is_float($value) || \is_int($value),
            'text', 'textarea', 'password' => \is_string($value),
            'single-select' => \is_string($value),
            'multi-select' => $this->isStringList($value),
            default => false,
        };

        if (!$valid) {
            throw ConfigurationException::invalidValue($field->key, $field->type, $value);
        }

        if (\in_array($field->type, ['single-select', 'multi-select'], true)) {
            $this->validateOptions($field, $value);
        }

        $this->validateConstraints($field, $value);
    }

    private function validateOptions(FieldDefinition $field, mixed $value): void
    {
        $allowed = array_map(
            static fn (int|string $key): string => (string) $key,
            array_keys($field->config['options'] ?? []),
        );
        $values = \is_array($value) ? $value : [$value];

        foreach ($values as $selected) {
            if (!\in_array((string) $selected, $allowed, true)) {
                throw ConfigurationException::invalidValue(
                    $field->key,
                    'one of [' . implode(', ', $allowed) . ']',
                    $selected,
                );
            }
        }
    }

    private function isStringList(mixed $value): bool
    {
        if (!\is_array($value) || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!\is_string($item)) {
                return false;
            }
        }

        return true;
    }

    private function validateConstraints(FieldDefinition $field, mixed $value): void
    {
        if ((\is_int($value) || \is_float($value))
            && isset($field->config['min'])
            && $value < $field->config['min']
        ) {
            throw ConfigurationException::invalidValue(
                $field->key,
                \sprintf('a value greater than or equal to %s', $field->config['min']),
                $value,
            );
        }

        if ((\is_int($value) || \is_float($value))
            && isset($field->config['max'])
            && $value > $field->config['max']
        ) {
            throw ConfigurationException::invalidValue(
                $field->key,
                \sprintf('a value less than or equal to %s', $field->config['max']),
                $value,
            );
        }

        if (!\is_string($value)) {
            return;
        }

        $length = mb_strlen($value);
        if (isset($field->config['minLength']) && $length < $field->config['minLength']) {
            throw ConfigurationException::invalidValue(
                $field->key,
                \sprintf('at least %d characters', $field->config['minLength']),
                $value,
            );
        }

        if (isset($field->config['maxLength']) && $length > $field->config['maxLength']) {
            throw ConfigurationException::invalidValue(
                $field->key,
                \sprintf('at most %d characters', $field->config['maxLength']),
                $value,
            );
        }
    }
}
