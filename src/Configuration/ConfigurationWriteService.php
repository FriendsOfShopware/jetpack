<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
final class ConfigurationWriteService
{
    public function __construct(
        private readonly ConfigurationDefinitionRegistry $definitions,
        private readonly ConfigurationStore $store,
        private readonly ValueTypeValidator $valueValidator,
        private readonly DefaultConfigurationService $configuration,
    ) {
    }

    /**
     * @param list<StoredConfigurationValue> $writes
     * @param list<ConfigurationAddress> $deletes
     */
    public function apply(string $bundle, array $writes, array $deletes): void
    {
        $definition = $this->definitions->get($bundle);

        foreach ($writes as $write) {
            $field = $definition->field($write->address->key);
            $this->validateAddress($field, $write->address);
            $this->valueValidator->validate($field, $write->value);
        }

        foreach ($deletes as $delete) {
            $this->validateAddress($definition->field($delete->key), $delete);
        }

        $this->store->apply($definition->bundle->name, $writes, $deletes);
        $this->configuration->reset();
    }

    private function validateAddress(FieldDefinition $field, ConfigurationAddress $address): void
    {
        if ($address->salesChannelId !== null && !Uuid::isValid($address->salesChannelId)) {
            throw ConfigurationException::invalidValue($field->key, 'a valid sales-channel UUID', $address->salesChannelId);
        }

        if ($address->languageId !== null && !Uuid::isValid($address->languageId)) {
            throw ConfigurationException::invalidValue($field->key, 'a valid language UUID', $address->languageId);
        }

        if ($address->salesChannelId !== null && !$field->scope->usesSalesChannel()) {
            throw ConfigurationException::invalidValue($field->key, 'a scope without a sales channel', $address->salesChannelId);
        }

        if ($address->languageId !== null && !$field->scope->usesLanguage()) {
            throw ConfigurationException::invalidValue($field->key, 'a scope without a language', $address->languageId);
        }
    }
}
