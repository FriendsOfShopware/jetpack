<?php declare(strict_types=1);

namespace Frosh\Jetpack\ScheduledTask;

/**
 * @internal
 */
final class ScheduledTaskNameGenerator
{
    public function generate(string $class, ?string $explicitName): string
    {
        if ($explicitName !== null) {
            return $explicitName;
        }

        return implode('.', array_map($this->snakeCase(...), explode('\\', ltrim($class, '\\'))));
    }

    private function snakeCase(string $segment): string
    {
        $segment = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $segment) ?? $segment;
        $segment = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $segment) ?? $segment;

        return strtolower($segment);
    }
}
