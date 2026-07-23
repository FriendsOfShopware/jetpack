<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsScheduledTask
{
    public function __construct(
        public int $interval,
        public ?string $name = null,
        public bool $rescheduleOnFailure = false,
    ) {
    }
}
