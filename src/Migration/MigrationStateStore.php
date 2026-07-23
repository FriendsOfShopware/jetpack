<?php declare(strict_types=1);

namespace Frosh\Jetpack\Migration;

/**
 * @internal
 */
interface MigrationStateStore
{
    public function initialize(): void;

    /**
     * @return list<ExecutedMigration>
     */
    public function executed(string $bundle): array;

    /**
     * @param class-string<Migration> $class
     */
    public function markExecuted(string $bundle, string $class, int $creationTimestamp): void;

    /**
     * @param class-string<Migration> $class
     */
    public function remove(string $bundle, string $class): void;
}
