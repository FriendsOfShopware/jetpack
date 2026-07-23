<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Migration;

final readonly class MigrationPlan
{
    /**
     * @param list<SchemaOperation> $operations
     */
    public function __construct(
        public array $operations,
        public bool $declarationsChanged = false,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->operations === [];
    }

    public function hasDeclarationChanges(): bool
    {
        return $this->declarationsChanged;
    }

    /**
     * @return list<SchemaOperation>
     */
    public function safe(): array
    {
        return array_values(array_filter($this->operations, static fn (SchemaOperation $operation): bool => !$operation->destructive));
    }

    /**
     * @return list<SchemaOperation>
     */
    public function destructive(): array
    {
        return array_values(array_filter($this->operations, static fn (SchemaOperation $operation): bool => $operation->destructive));
    }
}
