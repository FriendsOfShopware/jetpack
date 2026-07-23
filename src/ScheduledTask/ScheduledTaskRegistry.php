<?php declare(strict_types=1);

namespace Frosh\Jetpack\ScheduledTask;

use Shopware\Core\Framework\Bundle;

/**
 * @internal
 */
final class ScheduledTaskRegistry
{
    /**
     * @param iterable<ScheduledTaskDescriptor> $descriptors
     */
    public function __construct(private readonly iterable $descriptors)
    {
    }

    /**
     * @return list<ScheduledTaskDescriptor>
     */
    public function all(): array
    {
        $descriptors = $this->descriptors instanceof \Traversable
            ? iterator_to_array($this->descriptors, false)
            : array_values($this->descriptors);
        usort(
            $descriptors,
            static fn (ScheduledTaskDescriptor $first, ScheduledTaskDescriptor $second): int => $first->name <=> $second->name,
        );

        return $descriptors;
    }

    /**
     * @return list<ScheduledTaskDescriptor>
     */
    public function forBundle(Bundle $bundle): array
    {
        $resolvedPath = realpath($bundle->getPath());
        $bundlePath = $resolvedPath === false ? $bundle->getPath() : $resolvedPath;
        $prefix = rtrim($bundlePath, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR;

        return array_values(array_filter(
            $this->all(),
            static function (ScheduledTaskDescriptor $descriptor) use ($prefix): bool {
                $resolvedFile = realpath($descriptor->sourceFile);
                $file = $resolvedFile === false ? $descriptor->sourceFile : $resolvedFile;

                return str_starts_with($file, $prefix);
            },
        ));
    }
}
