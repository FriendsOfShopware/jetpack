<?php declare(strict_types=1);

namespace Frosh\Jetpack\Scaffolding\Generator;

use Frosh\Jetpack\Scaffolding\GeneratedFile;
use Frosh\Jetpack\Scaffolding\ScaffoldNaming;
use Frosh\Jetpack\Scaffolding\ScaffoldPlan;
use Frosh\Jetpack\Scaffolding\StubRenderer;
use Shopware\Core\Framework\Bundle;

/**
 * @internal
 */
final class ScheduledTaskScaffolder
{
    private const NAME_PATTERN = '/^[a-z][a-z0-9_-]*(?:\.[a-z][a-z0-9_-]*)+$/';

    public function __construct(private readonly StubRenderer $renderer)
    {
    }

    public function create(
        Bundle $bundle,
        string $name,
        int $interval,
        ?string $taskName,
        bool $rescheduleOnFailure,
    ): ScaffoldPlan {
        ScaffoldNaming::assertPascalCase($name, 'scheduled task');
        if ($interval < 1) {
            throw new \InvalidArgumentException('The scheduled-task interval must be at least 1 second.');
        }

        $taskName ??= $bundle->getContainerPrefix() . '.' . ScaffoldNaming::snakeCase($name);
        if (mb_strlen($taskName) > 255 || preg_match(self::NAME_PATTERN, $taskName) !== 1) {
            throw new \InvalidArgumentException('The scheduled-task name must contain at least two lower-case dot-separated segments and be at most 255 characters.');
        }

        $namespace = trim($bundle->getNamespace(), '\\') . '\\ScheduledTask';
        $content = $this->renderer->render(<<<'PHP'
<?php declare(strict_types=1);

namespace %jetpack.namespace%;

use Frosh\Jetpack\Attribute\AsScheduledTask;
use Shopware\Core\Framework\Context;

#[AsScheduledTask(
    interval: %jetpack.interval%,
    name: %jetpack.task_name%,
    rescheduleOnFailure: %jetpack.reschedule_on_failure%,
)]
final class %jetpack.name%
{
    public function __invoke(Context $context): void
    {
        // TODO: Implement the scheduled task.
    }
}

PHP, [
            'namespace' => $namespace,
            'interval' => (string) $interval,
            'task_name' => var_export($taskName, true),
            'reschedule_on_failure' => $rescheduleOnFailure ? 'true' : 'false',
            'name' => $name,
        ]);

        return new ScaffoldPlan(
            $bundle->getPath(),
            [new GeneratedFile('ScheduledTask/' . $name . '.php', $content)],
            \sprintf('Created scheduled task %s.', $name),
            ['Ensure the bundle autowires and autoconfigures the generated class, then rebuild the container.'],
        );
    }
}
