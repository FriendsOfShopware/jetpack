<?php declare(strict_types=1);

namespace Frosh\Jetpack\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
final class ScheduledTaskProxyGenerator
{
    public const RELATIVE_FILE = 'frosh-jetpack/scheduled-tasks.php';

    private const GENERATED_NAMESPACE = 'Frosh\\Jetpack\\Generated\\ScheduledTask';

    public function __construct(private readonly Filesystem $filesystem = new Filesystem())
    {
    }

    /**
     * @return class-string<ScheduledTask>
     */
    public function className(string $serviceClass, string $taskName): string
    {
        $class = self::GENERATED_NAMESPACE . '\\Task_' . substr(hash('sha256', $serviceClass . "\0" . $taskName), 0, 24);

        /** @var class-string<ScheduledTask> $class */
        return $class;
    }

    /**
     * @param list<ScheduledTaskDescriptor> $descriptors
     */
    public function write(string $cacheDirectory, array $descriptors): string
    {
        $path = rtrim($cacheDirectory, '/\\') . \DIRECTORY_SEPARATOR . self::RELATIVE_FILE;
        $this->filesystem->dumpFile($path, $this->render($descriptors));

        return $path;
    }

    /**
     * @param list<ScheduledTaskDescriptor> $descriptors
     */
    public function render(array $descriptors): string
    {
        usort(
            $descriptors,
            static fn (ScheduledTaskDescriptor $first, ScheduledTaskDescriptor $second): int => $first->proxyClass <=> $second->proxyClass,
        );

        $source = "<?php declare(strict_types=1);\n\nnamespace " . self::GENERATED_NAMESPACE . ";\n\n";
        $metadata = [];
        foreach ($descriptors as $descriptor) {
            $position = strrpos($descriptor->proxyClass, '\\');
            $shortClass = $position === false ? $descriptor->proxyClass : substr($descriptor->proxyClass, $position + 1);
            $source .= \sprintf(
                "if (!class_exists(%1\$s::class, false)) {\n    final class %1\$s extends \\%2\$s\n    {\n        public static function getTaskName(): string\n        {\n            return \\%3\$s::name(self::class);\n        }\n\n        public static function getDefaultInterval(): int\n        {\n            return \\%3\$s::interval(self::class);\n        }\n\n        public static function shouldRescheduleOnFailure(): bool\n        {\n            return \\%3\$s::rescheduleOnFailure(self::class);\n        }\n    }\n}\n\n",
                $shortClass,
                ScheduledTask::class,
                ScheduledTaskMetadata::class,
            );
            $metadata[$descriptor->proxyClass] = [
                'name' => $descriptor->name,
                'interval' => $descriptor->interval,
                'rescheduleOnFailure' => $descriptor->rescheduleOnFailure,
            ];
        }

        $source .= '\\' . ScheduledTaskMetadata::class . '::replace(' . var_export($metadata, true) . ");\n";

        return $source;
    }
}
