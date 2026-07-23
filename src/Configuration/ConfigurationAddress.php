<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

/**
 * @internal
 */
final readonly class ConfigurationAddress
{
    public function __construct(
        public string $key,
        public ?string $salesChannelId,
        public ?string $languageId,
    ) {
    }

    public function scopeKey(): string
    {
        return hash('sha256', ($this->salesChannelId ?? 'global') . '|' . ($this->languageId ?? 'global'), true);
    }
}
