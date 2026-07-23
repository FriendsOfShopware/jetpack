<?php declare(strict_types=1);

namespace Frosh\Jetpack\ScheduledTask;

/**
 * @internal
 */
final readonly class ScheduledTaskDescriptor
{
    /**
     * @param class-string $serviceClass
     * @param class-string $proxyClass
     */
    public function __construct(
        public string $serviceId,
        public string $serviceClass,
        public string $name,
        public int $interval,
        public bool $rescheduleOnFailure,
        public string $proxyClass,
        public string $sourceFile,
    ) {
    }
}
