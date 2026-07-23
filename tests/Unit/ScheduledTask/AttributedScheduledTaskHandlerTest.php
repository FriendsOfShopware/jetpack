<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\ScheduledTask;

use Frosh\Jetpack\ScheduledTask\AttributedScheduledTaskHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

#[CoversClass(AttributedScheduledTaskHandler::class)]
final class AttributedScheduledTaskHandlerTest extends TestCase
{
    public function testInvokesTheConsumerWithAFreshCliContext(): void
    {
        $consumer = new RecordingScheduledTask();
        $handler = new AttributedScheduledTaskHandler(
            static::createStub(EntityRepository::class),
            new NullLogger(),
            $consumer,
        );

        $handler->run();
        $handler->run();

        static::assertCount(2, $consumer->contexts);
        static::assertNotSame($consumer->contexts[0], $consumer->contexts[1]);
    }
}

final class RecordingScheduledTask
{
    /**
     * @var list<Context>
     */
    public array $contexts = [];

    public function __invoke(Context $context): void
    {
        $this->contexts[] = $context;
    }
}
