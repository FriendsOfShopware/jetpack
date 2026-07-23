<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Migration;

use Frosh\Jetpack\Entity\Snapshot\Snapshot;

final readonly class GenerationResult
{
    public function __construct(
        public MigrationPlan $plan,
        public ?string $migrationPath,
        public ?Snapshot $snapshot,
    ) {
    }
}
