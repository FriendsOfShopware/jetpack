<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

/**
 * @internal
 */
final readonly class StoredConfigurationValue
{
    public function __construct(
        public ConfigurationAddress $address,
        public mixed $value,
    ) {
    }
}
