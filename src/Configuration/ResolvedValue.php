<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

final readonly class ResolvedValue
{
    public function __construct(
        public mixed $value,
        public ?string $salesChannelId,
        public ?string $languageId,
        public bool $inherited,
        public bool $default,
    ) {
    }
}
