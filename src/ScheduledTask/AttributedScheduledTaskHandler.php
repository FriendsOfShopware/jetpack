<?php declare(strict_types=1);

namespace Frosh\Jetpack\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @internal
 */
final class AttributedScheduledTaskHandler extends ScheduledTaskHandler
{
    private readonly \Closure $task;

    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     * @param callable(Context): void $task
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $exceptionLogger,
        callable $task,
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);

        $this->task = $task(...);
    }

    public function run(): void
    {
        ($this->task)(Context::createCLIContext());
    }
}
