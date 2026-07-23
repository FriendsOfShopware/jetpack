<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Snapshot;

use Frosh\Jetpack\Entity\Schema\BundleSchema;

final readonly class Snapshot
{
    public function __construct(
        public string $id,
        public ?string $previousId,
        public int $timestamp,
        public string $name,
        public ?string $migrationClass,
        public BundleSchema $schema,
    ) {
    }
}
