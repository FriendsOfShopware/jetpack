<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Snapshot;

use Frosh\Jetpack\Entity\Schema\BundleSchema;
use Shopware\Core\Framework\Bundle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
final class SchemaSnapshotStore
{
    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    public function latest(Bundle $bundle): ?Snapshot
    {
        $snapshots = $this->all($bundle);
        if ($snapshots === []) {
            return null;
        }

        return $snapshots[array_key_last($snapshots)];
    }

    /**
     * @return list<Snapshot>
     */
    public function all(Bundle $bundle): array
    {
        $journal = $this->readJournal($bundle);
        $snapshots = [];
        $ids = [];
        $files = [];
        $previous = null;
        $previousTimestamp = null;
        foreach ($journal['entries'] as $entry) {
            $id = $this->string($entry, 'id');
            $filename = $this->string($entry, 'snapshot');
            if (basename($filename) !== $filename || !str_ends_with($filename, '.json')) {
                throw new \RuntimeException(\sprintf('Unsafe Jetpack entity snapshot filename "%s".', $filename));
            }
            if (isset($ids[$id]) || isset($files[$filename])) {
                throw new \RuntimeException(\sprintf('Duplicate Jetpack entity snapshot journal entry "%s".', $id));
            }
            $snapshot = $this->readSnapshot($bundle, $filename);
            if ($snapshot->id !== $id || $snapshot->previousId !== $previous) {
                throw new \RuntimeException(\sprintf('Jetpack entity snapshot chain is broken at "%s".', $id));
            }
            if (($entry['fingerprint'] ?? null) !== $snapshot->schema->fingerprint()
                || ($entry['migrationClass'] ?? null) !== $snapshot->migrationClass
            ) {
                throw new \RuntimeException(\sprintf('Jetpack entity snapshot journal metadata does not match "%s".', $id));
            }
            if ($previousTimestamp !== null && $snapshot->timestamp <= $previousTimestamp) {
                throw new \RuntimeException(\sprintf('Jetpack entity snapshot timestamps are not strictly increasing at "%s".', $id));
            }
            $ids[$id] = true;
            $files[$filename] = true;
            $previous = $id;
            $previousTimestamp = $snapshot->timestamp;
            $snapshots[] = $snapshot;
        }

        return $snapshots;
    }

    public function append(
        Bundle $bundle,
        BundleSchema $schema,
        int $timestamp,
        string $name,
        ?string $migrationClass,
    ): Snapshot {
        $latest = $this->latest($bundle);
        if ($latest !== null && $timestamp <= $latest->timestamp) {
            throw new \RuntimeException(\sprintf('Snapshot timestamp %d must be newer than %d.', $timestamp, $latest->timestamp));
        }
        $slug = $this->slug($name);
        $id = $timestamp . '_' . $slug;
        $filename = $id . '.json';
        $snapshot = new Snapshot($id, $latest?->id, $timestamp, $name, $migrationClass, $schema);
        $directory = $this->directory($bundle);
        $this->filesystem->mkdir($directory);
        $snapshotPath = Path::join($directory, $filename);
        if (is_file($snapshotPath)) {
            throw new \RuntimeException(\sprintf('Jetpack entity snapshot "%s" already exists.', $snapshotPath));
        }
        $document = [
            'formatVersion' => 1,
            'id' => $id,
            'previousId' => $latest?->id,
            'timestamp' => $timestamp,
            'name' => $name,
            'migrationClass' => $migrationClass,
            'fingerprint' => $schema->fingerprint(),
            'schema' => $schema->toArray(),
        ];
        $this->filesystem->dumpFile(
            $snapshotPath,
            json_encode($document, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR) . "\n",
        );

        try {
            $journal = $this->readJournal($bundle);
            $journal['entries'][] = [
                'id' => $id,
                'snapshot' => $filename,
                'migrationClass' => $migrationClass,
                'fingerprint' => $schema->fingerprint(),
            ];
            $this->filesystem->dumpFile(
                Path::join($directory, '_journal.json'),
                json_encode($journal, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR) . "\n",
            );
        } catch (\Throwable $exception) {
            $this->filesystem->remove($snapshotPath);

            throw $exception;
        }

        return $snapshot;
    }

    public function directory(Bundle $bundle): string
    {
        return Path::join($bundle->getPath(), 'Resources', 'jetpack', 'entity-schema');
    }

    /**
     * @return array{formatVersion: int, entries: list<array<string, mixed>>}
     */
    private function readJournal(Bundle $bundle): array
    {
        $path = Path::join($this->directory($bundle), '_journal.json');
        if (!is_file($path)) {
            return ['formatVersion' => 1, 'entries' => []];
        }
        $journal = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($journal) || ($journal['formatVersion'] ?? null) !== 1 || !\is_array($journal['entries'] ?? null)) {
            throw new \RuntimeException(\sprintf('Jetpack entity snapshot journal "%s" is invalid.', $path));
        }

        foreach ($journal['entries'] as $entry) {
            if (!\is_array($entry)) {
                throw new \RuntimeException(\sprintf('Jetpack entity snapshot journal "%s" contains an invalid entry.', $path));
            }
        }

        return ['formatVersion' => 1, 'entries' => array_values($journal['entries'])];
    }

    private function readSnapshot(Bundle $bundle, string $filename): Snapshot
    {
        $path = Path::join($this->directory($bundle), $filename);
        if (!is_file($path)) {
            throw new \RuntimeException(\sprintf('Jetpack entity snapshot "%s" is missing.', $path));
        }
        $document = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($document)
            || ($document['formatVersion'] ?? null) !== 1
            || !\is_array($document['schema'] ?? null)
        ) {
            throw new \RuntimeException(\sprintf('Jetpack entity snapshot "%s" is invalid.', $path));
        }
        $snapshot = new Snapshot(
            id: $this->string($document, 'id'),
            previousId: $this->nullableString($document, 'previousId'),
            timestamp: $this->int($document, 'timestamp'),
            name: $this->string($document, 'name'),
            migrationClass: $this->nullableString($document, 'migrationClass'),
            schema: BundleSchema::fromArray($document['schema']),
        );
        if (($document['fingerprint'] ?? null) !== $snapshot->schema->fingerprint()) {
            throw new \RuntimeException(\sprintf('Jetpack entity snapshot "%s" fingerprint does not match its schema.', $path));
        }

        return $snapshot;
    }

    private function slug(string $name): string
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', trim($name)) ?? $name);
        $slug = trim($slug, '_');

        return $slug === '' ? 'entity_schema' : $slug;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function string(array $data, string $key): string
    {
        if (!\is_string($data[$key] ?? null)) {
            throw new \RuntimeException(\sprintf('Snapshot key "%s" must be a string.', $key));
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !\is_string($value)) {
            throw new \RuntimeException(\sprintf('Snapshot key "%s" must be a string or null.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function int(array $data, string $key): int
    {
        if (!\is_int($data[$key] ?? null)) {
            throw new \RuntimeException(\sprintf('Snapshot key "%s" must be an int.', $key));
        }

        return $data[$key];
    }
}
