<?php declare(strict_types=1);

namespace Frosh\Jetpack\ScheduledTask;

use Frosh\Jetpack\Attribute\AsScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
final class ScheduledTaskCompilerPass implements CompilerPassInterface
{
    public const DESCRIPTOR_TAG = 'frosh.jetpack.scheduled_task';

    public function __construct(
        private readonly ScheduledTaskNameGenerator $nameGenerator = new ScheduledTaskNameGenerator(),
        private readonly ScheduledTaskDeclarationValidator $validator = new ScheduledTaskDeclarationValidator(),
        private readonly ScheduledTaskProxyGenerator $proxyGenerator = new ScheduledTaskProxyGenerator(),
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        $descriptors = $this->discover($container);
        $cacheDirectory = $container->getParameter('kernel.cache_dir');
        if (!\is_string($cacheDirectory) || $cacheDirectory === '') {
            throw new \LogicException('The kernel.cache_dir parameter must be a non-empty string.');
        }

        $generatedFile = $this->proxyGenerator->write($cacheDirectory, $descriptors);
        require $generatedFile;

        foreach ($descriptors as $descriptor) {
            $hash = substr(hash('sha256', $descriptor->proxyClass), 0, 24);
            $taskServiceId = 'frosh.jetpack.generated_scheduled_task.' . $hash;
            $handlerServiceId = 'frosh.jetpack.generated_scheduled_task_handler.' . $hash;
            $descriptorServiceId = 'frosh.jetpack.scheduled_task_descriptor.' . $hash;

            $container->setDefinition(
                $taskServiceId,
                (new Definition($descriptor->proxyClass))
                    ->setFile($generatedFile)
                    ->addTag('shopware.scheduled.task'),
            );
            $container->setDefinition(
                $handlerServiceId,
                (new Definition(AttributedScheduledTaskHandler::class, [
                    new Reference('scheduled_task.repository'),
                    new Reference('logger'),
                    new Reference($descriptor->serviceId),
                ]))->addTag('messenger.message_handler', ['handles' => $descriptor->proxyClass]),
            );
            $container->setDefinition(
                $descriptorServiceId,
                (new Definition(ScheduledTaskDescriptor::class, [
                    $descriptor->serviceId,
                    $descriptor->serviceClass,
                    $descriptor->name,
                    $descriptor->interval,
                    $descriptor->rescheduleOnFailure,
                    $descriptor->proxyClass,
                    $descriptor->sourceFile,
                ]))->addTag(self::DESCRIPTOR_TAG),
            );
        }
    }

    /**
     * @return list<ScheduledTaskDescriptor>
     */
    private function discover(ContainerBuilder $container): array
    {
        $descriptors = [];
        $servicesByClass = [];
        $classesByName = $this->nativeTaskNames($container);

        foreach ($container->getDefinitions() as $serviceId => $definition) {
            $class = $definition->getClass();
            if ($class === null) {
                continue;
            }
            $class = $container->getParameterBag()->resolveValue($class);
            if (!\is_string($class) || !class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            $attributes = $reflection->getAttributes(AsScheduledTask::class);
            if ($attributes === []) {
                continue;
            }
            if (\count($attributes) !== 1) {
                throw ScheduledTaskException::invalid($class, 'attribute must be declared exactly once.');
            }
            if (isset($servicesByClass[$class])) {
                throw ScheduledTaskException::duplicateService($class, $servicesByClass[$class], $serviceId);
            }

            $attribute = $attributes[0]->newInstance();
            $name = $this->nameGenerator->generate($class, $attribute->name);
            $this->validator->validate($reflection, $attribute, $name);

            $existingClass = $classesByName[$name] ?? null;
            if ($existingClass !== null) {
                throw ScheduledTaskException::duplicateName($name, $existingClass, $class);
            }

            $sourceFile = $reflection->getFileName();
            if ($sourceFile === false) {
                throw ScheduledTaskException::invalid($class, 'source file could not be resolved.');
            }

            $descriptors[] = new ScheduledTaskDescriptor(
                $serviceId,
                $class,
                $name,
                $attribute->interval,
                $attribute->rescheduleOnFailure,
                $this->proxyGenerator->className($class, $name),
                $sourceFile,
            );
            $servicesByClass[$class] = $serviceId;
            $classesByName[$name] = $class;
        }

        usort(
            $descriptors,
            static fn (ScheduledTaskDescriptor $first, ScheduledTaskDescriptor $second): int => $first->name <=> $second->name,
        );

        return $descriptors;
    }

    /**
     * @return array<string, class-string<ScheduledTask>>
     */
    private function nativeTaskNames(ContainerBuilder $container): array
    {
        $classesByName = [];

        foreach ($container->getDefinitions() as $definition) {
            $class = $definition->getClass();
            if ($class === null) {
                continue;
            }

            $class = $container->getParameterBag()->resolveValue($class);
            if (!\is_string($class) || !class_exists($class) || !is_subclass_of($class, ScheduledTask::class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }

            /** @var class-string<ScheduledTask> $class */
            $classesByName[$class::getTaskName()] ??= $class;
        }

        return $classesByName;
    }
}
