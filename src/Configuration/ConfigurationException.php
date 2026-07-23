<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

final class ConfigurationException extends \RuntimeException
{
    public static function bundleNotConfigured(string $bundle): self
    {
        return new self(\sprintf('Bundle "%s" does not provide Resources/config/jetpack.yaml.', $bundle));
    }

    public static function invalidDefinition(string $path, string $message): self
    {
        return new self(\sprintf('Invalid Jetpack configuration in "%s": %s', $path, $message));
    }

    public static function unknownKey(string $bundle, string $key): self
    {
        return new self(\sprintf('Unknown Jetpack configuration key "%s" for bundle "%s".', $key, $bundle));
    }

    public static function invalidValue(string $key, string $expected, mixed $value): self
    {
        return new self(\sprintf(
            'Jetpack configuration key "%s" expects %s, got %s.',
            $key,
            $expected,
            get_debug_type($value),
        ));
    }

    public static function invalidDto(string $class, string $message): self
    {
        return new self(\sprintf('Cannot hydrate Jetpack configuration DTO "%s": %s', $class, $message));
    }
}
