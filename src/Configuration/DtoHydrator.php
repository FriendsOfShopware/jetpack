<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

use Frosh\Jetpack\Attribute\ConfigKey;
use Frosh\Jetpack\Attribute\JetpackConfig;

/**
 * @internal
 */
final class DtoHydrator
{
    /**
     * @template TConfig of object
     *
     * @param class-string<TConfig> $configClass
     * @param \Closure(string): mixed $valueProvider
     *
     * @return TConfig
     */
    public function hydrate(string $configClass, \Closure $valueProvider): object
    {
        $reflection = new \ReflectionClass($configClass);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            throw ConfigurationException::invalidDto($configClass, 'a constructor is required.');
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $attributes = $parameter->getAttributes(ConfigKey::class);
            $key = $attributes !== [] ? $attributes[0]->newInstance()->key : $parameter->getName();
            $arguments[] = $this->normalizeValue($configClass, $parameter, $valueProvider($key));
        }

        return $reflection->newInstanceArgs($arguments);
    }

    /**
     * @param class-string $configClass
     *
     * @return class-string
     */
    public function bundle(string $configClass): string
    {
        $reflection = new \ReflectionClass($configClass);
        $attributes = $reflection->getAttributes(JetpackConfig::class);

        if ($attributes === []) {
            throw ConfigurationException::invalidDto($configClass, 'the #[JetpackConfig] attribute is missing.');
        }

        return $attributes[0]->newInstance()->bundle;
    }

    /**
     * @param class-string $configClass
     */
    private function normalizeValue(string $configClass, \ReflectionParameter $parameter, mixed $value): mixed
    {
        $type = $parameter->getType();
        if (!$type instanceof \ReflectionNamedType) {
            throw ConfigurationException::invalidDto(
                $configClass,
                \sprintf('constructor parameter "$%s" must have one named type.', $parameter->getName()),
            );
        }

        if ($value === null) {
            if ($type->allowsNull()) {
                return null;
            }

            throw ConfigurationException::invalidDto(
                $configClass,
                \sprintf('constructor parameter "$%s" does not allow null.', $parameter->getName()),
            );
        }

        $typeName = $type->getName();
        if (enum_exists($typeName) && is_subclass_of($typeName, \BackedEnum::class)) {
            try {
                return $typeName::from($value);
            } catch (\TypeError|\ValueError) {
                throw ConfigurationException::invalidDto(
                    $configClass,
                    \sprintf('value for "$%s" is not a valid %s case.', $parameter->getName(), $typeName),
                );
            }
        }

        $valid = match ($typeName) {
            'mixed' => true,
            'bool' => \is_bool($value),
            'int' => \is_int($value),
            'float' => \is_float($value) || \is_int($value),
            'string' => \is_string($value),
            'array' => \is_array($value),
            default => false,
        };

        if (!$valid) {
            throw ConfigurationException::invalidDto(
                $configClass,
                \sprintf(
                    'constructor parameter "$%s" expects %s, got %s.',
                    $parameter->getName(),
                    $typeName,
                    get_debug_type($value),
                ),
            );
        }

        return $typeName === 'float' ? (float) $value : $value;
    }
}
