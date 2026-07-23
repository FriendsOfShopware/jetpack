<?php declare(strict_types=1);

namespace Frosh\Jetpack\Scaffolding;

/**
 * @internal
 */
final class ScaffoldNaming
{
    public static function assertPascalCase(string $name, string $subject): void
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) !== 1) {
            throw new \InvalidArgumentException(\sprintf(
                'The %s name must be PascalCase and contain only letters and digits.',
                $subject,
            ));
        }
    }

    public static function assertLowerSnakeCase(string $name, string $subject): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException(\sprintf('The %s name must be lower snake_case.', $subject));
        }
    }

    public static function snakeCase(string $name): string
    {
        $name = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $name) ?? $name;
        $name = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $name) ?? $name;

        return strtolower($name);
    }

    public static function kebabCase(string $name): string
    {
        return str_replace('_', '-', self::snakeCase($name));
    }

    public static function humanize(string $name): string
    {
        return ucfirst(str_replace('_', ' ', self::snakeCase($name)));
    }
}
