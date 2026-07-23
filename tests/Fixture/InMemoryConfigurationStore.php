<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture;

use Frosh\Jetpack\Configuration\ConfigurationAddress;
use Frosh\Jetpack\Configuration\ConfigurationStore;
use Frosh\Jetpack\Configuration\StoredConfigurationValue;

final class InMemoryConfigurationStore implements ConfigurationStore
{
    /**
     * @var list<StoredConfigurationValue>
     */
    public array $values = [];

    public int $loadCalls = 0;

    public function load(string $bundleName): array
    {
        ++$this->loadCalls;

        return $this->values;
    }

    public function apply(string $bundleName, array $writes, array $deletes): void
    {
        foreach ($deletes as $delete) {
            $this->values = array_values(array_filter(
                $this->values,
                static fn (StoredConfigurationValue $stored): bool => !self::sameAddress($stored->address, $delete),
            ));
        }

        foreach ($writes as $write) {
            $this->values = array_values(array_filter(
                $this->values,
                static fn (StoredConfigurationValue $stored): bool => !self::sameAddress($stored->address, $write->address),
            ));
            $this->values[] = $write;
        }
    }

    public function deleteBundle(string $bundleName): void
    {
        $this->values = [];
    }

    private static function sameAddress(ConfigurationAddress $left, ConfigurationAddress $right): bool
    {
        return $left->key === $right->key
            && $left->salesChannelId === $right->salesChannelId
            && $left->languageId === $right->languageId;
    }
}
