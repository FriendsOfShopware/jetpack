<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

/**
 * @internal
 */
final readonly class FieldDefinition
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public string $key,
        public string $type,
        public ConfigurationScope $scope,
        public mixed $defaultValue,
        public array $config,
    ) {
    }
}
