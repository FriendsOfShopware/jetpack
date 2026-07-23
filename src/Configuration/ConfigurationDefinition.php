<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

/**
 * @internal
 */
final readonly class ConfigurationDefinition
{
    /**
     * @param array<string, FieldDefinition> $fields
     * @param array<string, mixed> $document
     */
    public function __construct(
        public BundleReference $bundle,
        public array $fields,
        public array $document,
    ) {
    }

    public function field(string $key): FieldDefinition
    {
        return $this->fields[$key] ?? throw ConfigurationException::unknownKey($this->bundle->name, $key);
    }
}
