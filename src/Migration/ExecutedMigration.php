<?php declare(strict_types=1);

namespace Frosh\Jetpack\Migration;

/**
 * @internal
 */
final readonly class ExecutedMigration
{
    /**
     * @param class-string<Migration> $class
     */
    public function __construct(
        public string $class,
        public int $creationTimestamp,
    ) {
    }
}
