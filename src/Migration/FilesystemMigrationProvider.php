<?php declare(strict_types=1);

namespace Frosh\Jetpack\Migration;

use Shopware\Core\Framework\Bundle;

/**
 * @internal
 */
final class FilesystemMigrationProvider implements MigrationProvider
{
    /**
     * @var \WeakMap<Bundle, list<Migration>>
     */
    private \WeakMap $cache;

    public function __construct()
    {
        $this->cache = new \WeakMap();
    }

    /**
     * @return list<Migration>
     */
    public function forBundle(Bundle $bundle): array
    {
        if (isset($this->cache[$bundle])) {
            return $this->cache[$bundle];
        }

        $directory = $bundle->getMigrationPath();
        if (!is_dir($directory)) {
            return $this->cache[$bundle] = [];
        }
        $files = scandir($directory, \SCANDIR_SORT_ASCENDING);
        if ($files === false) {
            throw new MigrationException(\sprintf('Cannot read migration directory "%s".', $directory));
        }

        $migrations = [];
        $timestamps = [];
        foreach ($files as $file) {
            $path = $directory . \DIRECTORY_SEPARATOR . $file;
            if (pathinfo($path, \PATHINFO_EXTENSION) !== 'php') {
                continue;
            }
            $class = $bundle->getMigrationNamespace() . '\\' . pathinfo($file, \PATHINFO_FILENAME);
            if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
                throw MigrationException::invalidClass($class, $path);
            }
            if (!is_subclass_of($class, Migration::class)) {
                continue;
            }
            $reflection = new \ReflectionClass($class);
            $constructor = $reflection->getConstructor();
            if (!$reflection->isInstantiable() || ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0)) {
                throw MigrationException::notInstantiable($class);
            }

            $migration = $reflection->newInstance();
            $timestamp = $migration->getCreationTimestamp();
            if ($timestamp <= 0) {
                throw MigrationException::invalidTimestamp($class, $timestamp);
            }
            if (isset($timestamps[$timestamp])) {
                throw MigrationException::duplicateTimestamp($bundle->getName(), $timestamp, $timestamps[$timestamp], $class);
            }
            $timestamps[$timestamp] = $class;
            $migrations[] = $migration;
        }

        usort($migrations, static fn (Migration $first, Migration $second): int => $first->getCreationTimestamp() <=> $second->getCreationTimestamp());

        return $this->cache[$bundle] = $migrations;
    }
}
