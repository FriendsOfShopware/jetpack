<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Migration;

use Frosh\Jetpack\Entity\Schema\FieldSchema;

/**
 * @internal
 */
final class MigrationPlanInverter
{
    public function invert(MigrationPlan $plan): MigrationPlan
    {
        $operations = [];
        foreach (array_reverse($plan->operations) as $operation) {
            array_push($operations, ...$this->invertOperation($operation));
        }

        return new MigrationPlan($operations, $plan->declarationsChanged);
    }

    /**
     * @return list<SchemaOperation>
     */
    private function invertOperation(SchemaOperation $operation): array
    {
        return match ($operation->type) {
            OperationType::CreateTable => [new SchemaOperation(
                OperationType::DropTable,
                $operation->table,
                true,
                entity: $operation->entity,
            )],
            OperationType::DropTable => $this->restoreTable($operation),
            OperationType::RenameTable => [new SchemaOperation(
                OperationType::RenameTable,
                $operation->previousTable ?? throw new \LogicException('Rename-table operation needs a previous table.'),
                true,
                entity: $operation->previousEntity,
                previousEntity: $operation->entity,
                previousTable: $operation->table,
            )],
            OperationType::AddColumn => [new SchemaOperation(
                OperationType::DropColumn,
                $operation->table,
                true,
                entity: $operation->entity,
                previousField: $operation->field,
            )],
            OperationType::DropColumn => [new SchemaOperation(
                OperationType::AddColumn,
                $operation->table,
                true,
                entity: $operation->entity,
                field: $operation->previousField,
            )],
            OperationType::RenameColumn => [new SchemaOperation(
                OperationType::RenameColumn,
                $operation->table,
                true,
                entity: $operation->entity,
                field: $operation->previousField,
                previousField: $operation->field,
            )],
            OperationType::AlterColumn => [new SchemaOperation(
                OperationType::AlterColumn,
                $operation->table,
                true,
                entity: $operation->previousEntity,
                field: $this->previousFieldOnCurrentColumn($operation),
                previousField: $operation->field,
            )],
            OperationType::AlterTableOptions => [new SchemaOperation(
                OperationType::AlterTableOptions,
                $operation->table,
                true,
                entity: $operation->previousEntity ?? throw new \LogicException('Alter-table-options operation needs a previous entity.'),
                previousEntity: $operation->entity,
            )],
            OperationType::AddIndex => [new SchemaOperation(
                OperationType::DropIndex,
                $operation->table,
                true,
                entity: $operation->entity,
                index: $operation->index,
            )],
            OperationType::DropIndex => [new SchemaOperation(
                OperationType::AddIndex,
                $operation->table,
                true,
                entity: $operation->entity,
                index: $operation->index,
            )],
            OperationType::AddForeignKey => [new SchemaOperation(
                OperationType::DropForeignKey,
                $operation->table,
                true,
                entity: $operation->entity,
                previousForeignKey: $operation->foreignKey,
            )],
            OperationType::DropForeignKey => [new SchemaOperation(
                OperationType::AddForeignKey,
                $operation->table,
                true,
                entity: $operation->previousEntity ?? $operation->entity,
                foreignKey: $operation->previousForeignKey,
            )],
            OperationType::AddPrimaryKey => [new SchemaOperation(
                OperationType::DropPrimaryKey,
                $operation->table,
                true,
                entity: $operation->entity,
            )],
            OperationType::DropPrimaryKey => [new SchemaOperation(
                OperationType::AddPrimaryKey,
                $operation->table,
                true,
                entity: $operation->previousEntity ?? throw new \LogicException('Drop-primary-key operation needs a previous entity.'),
            )],
        };
    }

    /**
     * @return list<SchemaOperation>
     */
    private function restoreTable(SchemaOperation $operation): array
    {
        $entity = $operation->entity ?? throw new \LogicException('Drop-table operation needs an entity.');
        $operations = [new SchemaOperation(
            OperationType::CreateTable,
            $operation->table,
            true,
            entity: $entity,
        )];
        foreach ($entity->indexes as $index) {
            $operations[] = new SchemaOperation(
                OperationType::AddIndex,
                $operation->table,
                true,
                entity: $entity,
                index: $index,
            );
        }

        return $operations;
    }

    private function previousFieldOnCurrentColumn(SchemaOperation $operation): FieldSchema
    {
        $previous = $operation->previousField ?? throw new \LogicException('Alter-column operation needs a previous field.');
        $current = $operation->field ?? throw new \LogicException('Alter-column operation needs a field.');

        return FieldSchema::fromArray([
            ...$previous->toArray(),
            'column' => $current->storageName,
        ]);
    }
}
