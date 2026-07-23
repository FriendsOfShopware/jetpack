<?php declare(strict_types=1);

namespace Frosh\Jetpack\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
final class ScheduledTaskMetadata
{
    /**
     * @var array<class-string<ScheduledTask>, array{name: string, interval: int, rescheduleOnFailure: bool}>
     */
    private static array $tasks = [];

    /**
     * @param array<class-string<ScheduledTask>, array{name: string, interval: int, rescheduleOnFailure: bool}> $tasks
     */
    public static function replace(array $tasks): void
    {
        self::$tasks = $tasks;
    }

    /**
     * @param class-string<ScheduledTask> $class
     */
    public static function name(string $class): string
    {
        return self::get($class)['name'];
    }

    /**
     * @param class-string<ScheduledTask> $class
     */
    public static function interval(string $class): int
    {
        return self::get($class)['interval'];
    }

    /**
     * @param class-string<ScheduledTask> $class
     */
    public static function rescheduleOnFailure(string $class): bool
    {
        return self::get($class)['rescheduleOnFailure'];
    }

    /**
     * @param class-string<ScheduledTask> $class
     *
     * @return array{name: string, interval: int, rescheduleOnFailure: bool}
     */
    private static function get(string $class): array
    {
        return self::$tasks[$class] ?? throw new \LogicException(\sprintf(
            'No Frosh Jetpack scheduled-task metadata is registered for "%s".',
            $class,
        ));
    }
}
