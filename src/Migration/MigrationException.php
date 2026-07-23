<?php declare(strict_types=1);

namespace Frosh\Jetpack\Migration;

final class MigrationException extends \RuntimeException
{
    public static function invalidClass(string $class, string $path): self
    {
        return new self(\sprintf('Migration file "%s" does not declare class "%s".', $path, $class));
    }

    public static function notInstantiable(string $class): self
    {
        return new self(\sprintf('Jetpack migration "%s" must be instantiable without constructor arguments.', $class));
    }

    public static function invalidTimestamp(string $class, int $timestamp): self
    {
        return new self(\sprintf('Jetpack migration "%s" has invalid creation timestamp %d.', $class, $timestamp));
    }

    public static function duplicateTimestamp(string $bundle, int $timestamp, string $first, string $second): self
    {
        return new self(\sprintf(
            'Bundle "%s" contains two Jetpack migrations with timestamp %d: "%s" and "%s".',
            $bundle,
            $timestamp,
            $first,
            $second,
        ));
    }

    public static function missingAppliedMigration(string $bundle, string $class): self
    {
        return new self(\sprintf(
            'Applied Jetpack migration "%s" for bundle "%s" is no longer available. Restore the migration class before continuing.',
            $class,
            $bundle,
        ));
    }

    public static function changedTimestamp(string $class, int $recorded, int $declared): self
    {
        return new self(\sprintf(
            'Applied Jetpack migration "%s" changed its creation timestamp from %d to %d. Restore the recorded timestamp.',
            $class,
            $recorded,
            $declared,
        ));
    }

    public static function outOfOrder(string $bundle, string $class, int $timestamp, int $latestApplied): self
    {
        return new self(\sprintf(
            'Jetpack migration "%s" (%d) for bundle "%s" is older than the latest applied migration (%d). Add new migrations with a later timestamp.',
            $class,
            $timestamp,
            $bundle,
            $latestApplied,
        ));
    }

    public static function lockNotAcquired(string $bundle): self
    {
        return new self(\sprintf('Could not acquire the Jetpack migration lock for bundle "%s".', $bundle));
    }
}
