<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\ScheduledTask;

use Frosh\Jetpack\Attribute\AsScheduledTask;
use Frosh\Jetpack\FroshJetpack;
use Frosh\Jetpack\ScheduledTask\AttributedScheduledTaskHandler;
use Frosh\Jetpack\ScheduledTask\ScheduledTaskCompilerPass;
use Frosh\Jetpack\ScheduledTask\ScheduledTaskDeclarationValidator;
use Frosh\Jetpack\ScheduledTask\ScheduledTaskDescriptor;
use Frosh\Jetpack\ScheduledTask\ScheduledTaskException;
use Frosh\Jetpack\ScheduledTask\ScheduledTaskMetadata;
use Frosh\Jetpack\ScheduledTask\ScheduledTaskNameGenerator;
use Frosh\Jetpack\ScheduledTask\ScheduledTaskProxyGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(AsScheduledTask::class)]
#[CoversClass(FroshJetpack::class)]
#[CoversClass(ScheduledTaskCompilerPass::class)]
#[CoversClass(ScheduledTaskDeclarationValidator::class)]
#[CoversClass(ScheduledTaskDescriptor::class)]
#[CoversClass(ScheduledTaskException::class)]
#[CoversClass(ScheduledTaskMetadata::class)]
#[CoversClass(ScheduledTaskNameGenerator::class)]
#[CoversClass(ScheduledTaskProxyGenerator::class)]
final class ScheduledTaskCompilerPassTest extends TestCase
{
    private string $cacheDirectory;

    protected function setUp(): void
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/frosh-jetpack-scheduled-task-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($this->cacheDirectory);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->cacheDirectory);
    }

    public function testGeneratesAndWiresTheShopwareTaskAndHandler(): void
    {
        $container = $this->container();
        $container->register(ExampleCleanupTask::class, ExampleCleanupTask::class);

        (new ScheduledTaskCompilerPass())->process($container);

        $tasks = $container->findTaggedServiceIds('shopware.scheduled.task');
        static::assertCount(1, $tasks);
        $taskServiceId = array_key_first($tasks);
        $taskDefinition = $container->getDefinition($taskServiceId);
        $taskClass = $taskDefinition->getClass();
        static::assertIsString($taskClass);
        static::assertTrue(is_subclass_of($taskClass, ScheduledTask::class));
        static::assertSame('frosh.jetpack.tests.unit.scheduled_task.example_cleanup_task', $taskClass::getTaskName());
        static::assertSame(300, $taskClass::getDefaultInterval());
        static::assertFalse($taskClass::shouldRescheduleOnFailure());

        $handlers = $container->findTaggedServiceIds('messenger.message_handler');
        static::assertCount(1, $handlers);
        $handlerServiceId = array_key_first($handlers);
        $handlerDefinition = $container->getDefinition($handlerServiceId);
        static::assertSame(AttributedScheduledTaskHandler::class, $handlerDefinition->getClass());
        static::assertSame($taskClass, $handlerDefinition->getTag('messenger.message_handler')[0]['handles']);
        static::assertSame(ExampleCleanupTask::class, (string) $handlerDefinition->getArgument(2));

        $descriptors = $container->findTaggedServiceIds(ScheduledTaskCompilerPass::DESCRIPTOR_TAG);
        static::assertCount(1, $descriptors);
        $descriptorServiceId = array_key_first($descriptors);
        $descriptorDefinition = $container->getDefinition($descriptorServiceId);
        static::assertSame(ScheduledTaskDescriptor::class, $descriptorDefinition->getClass());
        static::assertFileExists($this->cacheDirectory . '/' . ScheduledTaskProxyGenerator::RELATIVE_FILE);
    }

    public function testPluginCompilerOrderingDiscoversAServiceUsingClassNameShorthand(): void
    {
        $container = $this->container();
        $container->setParameter('kernel.environment', 'prod');
        $container->register(ExampleCleanupTask::class);
        (new FroshJetpack(false, \dirname(__DIR__, 3)))->build($container);

        $resolveClassIndex = null;
        $scheduledTaskIndex = null;
        foreach ($container->getCompilerPassConfig()->getBeforeOptimizationPasses() as $index => $pass) {
            if ($pass instanceof ResolveClassPass) {
                $resolveClassIndex = $index;
            }
            if ($pass instanceof ScheduledTaskCompilerPass) {
                $scheduledTaskIndex = $index;
            }
        }

        static::assertIsInt($resolveClassIndex);
        static::assertIsInt($scheduledTaskIndex);
        static::assertLessThan($scheduledTaskIndex, $resolveClassIndex);

        (new ResolveClassPass())->process($container);
        (new ScheduledTaskCompilerPass())->process($container);

        $generatedFile = $this->cacheDirectory . '/' . ScheduledTaskProxyGenerator::RELATIVE_FILE;
        static::assertFileExists($generatedFile);
        static::assertStringContainsString(
            'frosh.jetpack.tests.unit.scheduled_task.example_cleanup_task',
            (string) file_get_contents($generatedFile),
        );
    }

    public function testPluginBootLoadsTheGeneratedProxyFile(): void
    {
        $generator = new ScheduledTaskProxyGenerator();
        $proxyClass = $generator->className(self::class, 'acme.boot');
        $generator->write($this->cacheDirectory, [new ScheduledTaskDescriptor(
            self::class,
            self::class,
            'acme.boot',
            300,
            false,
            $proxyClass,
            __FILE__,
        )]);
        static::assertFalse(class_exists($proxyClass, false));

        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', $this->cacheDirectory);
        $plugin = new FroshJetpack(false, \dirname(__DIR__, 3));
        $plugin->setContainer($container);
        $plugin->boot();

        static::assertTrue(class_exists($proxyClass, false));
        static::assertSame('acme.boot', $proxyClass::getTaskName());
    }

    public function testReloadsMetadataWhenTheStableProxyClassIsAlreadyLoaded(): void
    {
        $generator = new ScheduledTaskProxyGenerator();
        $proxyClass = $generator->className(self::class, 'acme.reload');
        $generatedFile = $generator->write($this->cacheDirectory, [new ScheduledTaskDescriptor(
            self::class,
            self::class,
            'acme.reload',
            300,
            false,
            $proxyClass,
            __FILE__,
        )]);
        require $generatedFile;

        static::assertSame(300, $proxyClass::getDefaultInterval());
        static::assertFalse($proxyClass::shouldRescheduleOnFailure());

        $generator->write($this->cacheDirectory, [new ScheduledTaskDescriptor(
            self::class,
            self::class,
            'acme.reload',
            900,
            true,
            $proxyClass,
            __FILE__,
        )]);
        require $generatedFile;

        static::assertSame(900, $proxyClass::getDefaultInterval());
        static::assertTrue($proxyClass::shouldRescheduleOnFailure());
    }

    public function testUsesExplicitMetadataAndStableProxyIdentity(): void
    {
        $attribute = new AsScheduledTask(
            interval: 900,
            name: 'acme.cleanup',
            rescheduleOnFailure: true,
        );
        static::assertSame(900, $attribute->interval);
        static::assertSame('acme.cleanup', $attribute->name);
        static::assertTrue($attribute->rescheduleOnFailure);

        $generator = new ScheduledTaskProxyGenerator();
        $first = $generator->className(ExampleCleanupTask::class, 'acme.cleanup');
        $intervalChanged = $generator->className(ExampleCleanupTask::class, 'acme.cleanup');
        $nameChanged = $generator->className(ExampleCleanupTask::class, 'acme.cleanup_v2');

        static::assertSame($first, $intervalChanged);
        static::assertNotSame($first, $nameChanged);

        $container = $this->container();
        $container->register(ExplicitCleanupTask::class, ExplicitCleanupTask::class);
        (new ScheduledTaskCompilerPass())->process($container);

        $tasks = $container->findTaggedServiceIds('shopware.scheduled.task');
        static::assertCount(1, $tasks);
        $taskServiceId = array_key_first($tasks);
        $taskClass = $container->getDefinition($taskServiceId)->getClass();
        static::assertIsString($taskClass);
        static::assertSame('acme.cleanup', $taskClass::getTaskName());
        static::assertSame(900, $taskClass::getDefaultInterval());
        static::assertTrue($taskClass::shouldRescheduleOnFailure());
    }

    public function testRejectsAnInvalidInterval(): void
    {
        $container = $this->container();
        $container->register(InvalidIntervalTask::class, InvalidIntervalTask::class);

        $this->expectExceptionObject(ScheduledTaskException::invalid(
            InvalidIntervalTask::class,
            'interval must be at least 1 second.',
        ));

        (new ScheduledTaskCompilerPass())->process($container);
    }

    public function testRejectsAnInvalidCallableSignature(): void
    {
        $container = $this->container();
        $container->register(InvalidCallableTask::class, InvalidCallableTask::class);

        $this->expectExceptionObject(ScheduledTaskException::invalid(
            InvalidCallableTask::class,
            'must declare public function __invoke(Context $context): void.',
        ));

        (new ScheduledTaskCompilerPass())->process($container);
    }

    public function testRejectsDuplicateEffectiveNames(): void
    {
        $container = $this->container();
        $container->register(FirstDuplicateTask::class, FirstDuplicateTask::class);
        $container->register(SecondDuplicateTask::class, SecondDuplicateTask::class);

        $this->expectExceptionObject(ScheduledTaskException::duplicateName(
            'acme.duplicate',
            FirstDuplicateTask::class,
            SecondDuplicateTask::class,
        ));

        (new ScheduledTaskCompilerPass())->process($container);
    }

    public function testRejectsANameUsedByANativeShopwareTask(): void
    {
        $container = $this->container();
        $container->register(NativeScheduledTask::class, NativeScheduledTask::class);
        $container->register(NativeNameCollisionTask::class, NativeNameCollisionTask::class);

        $this->expectExceptionObject(ScheduledTaskException::duplicateName(
            'acme.native',
            NativeScheduledTask::class,
            NativeNameCollisionTask::class,
        ));

        (new ScheduledTaskCompilerPass())->process($container);
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', $this->cacheDirectory);
        $container->register('scheduled_task.repository')->setSynthetic(true);
        $container->register('logger', LoggerInterface::class)->setSynthetic(true);

        return $container;
    }
}

#[AsScheduledTask(interval: 300)]
final class ExampleCleanupTask
{
    public function __invoke(Context $context): void
    {
    }
}

#[AsScheduledTask(interval: 900, name: 'acme.cleanup', rescheduleOnFailure: true)]
final class ExplicitCleanupTask
{
    public function __invoke(Context $context): void
    {
    }
}

#[AsScheduledTask(interval: 0)]
final class InvalidIntervalTask
{
    public function __invoke(Context $context): void
    {
    }
}

#[AsScheduledTask(interval: 300)]
final class InvalidCallableTask
{
    public function __invoke(): int
    {
        return 1;
    }
}

#[AsScheduledTask(interval: 300, name: 'acme.duplicate')]
final class FirstDuplicateTask
{
    public function __invoke(Context $context): void
    {
    }
}

#[AsScheduledTask(interval: 300, name: 'acme.duplicate')]
final class SecondDuplicateTask
{
    public function __invoke(Context $context): void
    {
    }
}

final class NativeScheduledTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'acme.native';
    }

    public static function getDefaultInterval(): int
    {
        return 60;
    }
}

#[AsScheduledTask(interval: 300, name: 'acme.native')]
final class NativeNameCollisionTask
{
    public function __invoke(Context $context): void
    {
    }
}
