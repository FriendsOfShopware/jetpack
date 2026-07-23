<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

/**
 * @internal
 */
final class ConfigurationValueResolver
{
    /**
     * @param list<StoredConfigurationValue> $storedValues
     * @param list<string> $languageIdChain
     */
    public function resolve(
        FieldDefinition $field,
        array $storedValues,
        array $languageIdChain,
        ?string $salesChannelId,
    ): ResolvedValue {
        $indexed = [];
        $requestedSalesChannelId = $field->scope->usesSalesChannel() ? $salesChannelId : null;
        $requestedLanguageId = $field->scope->usesLanguage() ? ($languageIdChain[0] ?? null) : null;

        foreach ($storedValues as $storedValue) {
            if ($storedValue->address->key !== $field->key) {
                continue;
            }

            $indexed[$this->index($storedValue->address->salesChannelId, $storedValue->address->languageId)] = $storedValue;
        }

        foreach ($this->candidates($field, $languageIdChain, $salesChannelId) as [$candidateSalesChannelId, $candidateLanguageId]) {
            $index = $this->index($candidateSalesChannelId, $candidateLanguageId);
            if (!isset($indexed[$index])) {
                continue;
            }

            $storedValue = $indexed[$index];

            return new ResolvedValue(
                $storedValue->value,
                $storedValue->address->salesChannelId,
                $storedValue->address->languageId,
                $storedValue->address->salesChannelId !== $requestedSalesChannelId
                    || $storedValue->address->languageId !== $requestedLanguageId,
                false,
            );
        }

        return new ResolvedValue($field->defaultValue, null, null, true, true);
    }

    /**
     * @param list<string> $languageIdChain
     *
     * @return list<array{0: ?string, 1: ?string}>
     */
    private function candidates(FieldDefinition $field, array $languageIdChain, ?string $salesChannelId): array
    {
        $salesChannelCandidates = $field->scope->usesSalesChannel() && $salesChannelId !== null
            ? [$salesChannelId, null]
            : [null];
        $languageCandidates = $field->scope->usesLanguage()
            ? [...$languageIdChain, null]
            : [null];

        $candidates = [];
        foreach ($salesChannelCandidates as $candidateSalesChannelId) {
            foreach ($languageCandidates as $candidateLanguageId) {
                $candidates[] = [$candidateSalesChannelId, $candidateLanguageId];
            }
        }

        return $candidates;
    }

    private function index(?string $salesChannelId, ?string $languageId): string
    {
        return ($salesChannelId ?? 'global') . '|' . ($languageId ?? 'global');
    }
}
