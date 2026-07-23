<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

use Shopware\Core\Framework\Context;

/**
 * @internal
 */
final class ConfigurationAdminReader
{
    public function __construct(
        private readonly ConfigurationDefinitionRegistry $definitions,
        private readonly ConfigurationStore $store,
        private readonly ConfigurationValueResolver $resolver,
    ) {
    }

    /**
     * @return list<array{name: string, class: string, label: mixed}>
     */
    public function list(): array
    {
        return array_map(static fn (ConfigurationDefinition $definition): array => [
            'name' => $definition->bundle->name,
            'class' => $definition->bundle->class,
            'label' => $definition->document['label'] ?? ['en-GB' => $definition->bundle->name],
        ], $this->definitions->all());
    }

    /**
     * @return array{bundle: array{name: string, class: string}, definition: array<string, mixed>, values: array<string, array<string, mixed>>}
     */
    public function read(
        string $bundle,
        Context $context,
        ?string $salesChannelId,
        ?string $languageId,
    ): array {
        $definition = $this->definitions->get($bundle);
        $storedValues = $this->store->load($definition->bundle->name);
        $languageIdChain = $languageId !== null ? $context->getLanguageIdChain() : [];
        $values = [];

        foreach ($definition->fields as $field) {
            $fieldSalesChannelId = $field->scope->usesSalesChannel() ? $salesChannelId : null;
            $fieldLanguageId = $field->scope->usesLanguage() ? $languageId : null;
            $resolved = $this->resolver->resolve($field, $storedValues, $languageIdChain, $fieldSalesChannelId);
            $override = $this->findOverride($storedValues, $field->key, $fieldSalesChannelId, $fieldLanguageId);
            $inherited = $override === null
                ? $resolved
                : $this->resolver->resolve(
                    $field,
                    array_values(array_filter(
                        $storedValues,
                        static fn (StoredConfigurationValue $storedValue): bool => $storedValue !== $override,
                    )),
                    $languageIdChain,
                    $fieldSalesChannelId,
                );

            $values[$field->key] = [
                'value' => $override !== null ? $override->value : $resolved->value,
                'overridden' => $override !== null,
                'source' => [
                    'salesChannelId' => $override?->address->salesChannelId ?? $resolved->salesChannelId,
                    'languageId' => $override?->address->languageId ?? $resolved->languageId,
                    'default' => $override === null && $resolved->default,
                ],
                'inheritedValue' => $inherited->value,
                'inheritedSource' => [
                    'salesChannelId' => $inherited->salesChannelId,
                    'languageId' => $inherited->languageId,
                    'default' => $inherited->default,
                ],
                'address' => [
                    'salesChannelId' => $fieldSalesChannelId,
                    'languageId' => $fieldLanguageId,
                ],
            ];
        }

        return [
            'bundle' => [
                'name' => $definition->bundle->name,
                'class' => $definition->bundle->class,
            ],
            'definition' => $definition->document,
            'values' => $values,
        ];
    }

    /**
     * @param list<StoredConfigurationValue> $storedValues
     */
    private function findOverride(
        array $storedValues,
        string $key,
        ?string $salesChannelId,
        ?string $languageId,
    ): ?StoredConfigurationValue {
        foreach ($storedValues as $storedValue) {
            if ($storedValue->address->key === $key
                && $storedValue->address->salesChannelId === $salesChannelId
                && $storedValue->address->languageId === $languageId
            ) {
                return $storedValue;
            }
        }

        return null;
    }
}
