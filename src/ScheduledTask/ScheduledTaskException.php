<?php declare(strict_types=1);

namespace Frosh\Jetpack\ScheduledTask;

/**
 * @internal
 */
final class ScheduledTaskException extends \RuntimeException
{
    public static function invalid(string $class, string $reason): self
    {
        return new self(\sprintf('[Frosh Jetpack Scheduled Task] %s: %s', $class, $reason));
    }

    public static function duplicateName(string $name, string $firstClass, string $secondClass): self
    {
        return new self(\sprintf(
            '[Frosh Jetpack Scheduled Task] Effective name "%s" is declared by "%s" and "%s".',
            $name,
            $firstClass,
            $secondClass,
        ));
    }

    public static function duplicateService(string $class, string $firstService, string $secondService): self
    {
        return new self(\sprintf(
            '[Frosh Jetpack Scheduled Task] %s: class is registered by both service "%s" and "%s".',
            $class,
            $firstService,
            $secondService,
        ));
    }
}
