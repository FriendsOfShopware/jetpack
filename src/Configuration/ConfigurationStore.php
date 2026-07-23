<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

/**
 * @internal
 */
interface ConfigurationStore
{
    /**
     * @return list<StoredConfigurationValue>
     */
    public function load(string $bundleName): array;

    /**
     * @param list<StoredConfigurationValue> $writes
     * @param list<ConfigurationAddress> $deletes
     */
    public function apply(string $bundleName, array $writes, array $deletes): void;

    public function deleteBundle(string $bundleName): void;
}
