<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Migration;

use Frosh\Jetpack\Entity\Schema\BundleSchema;
use Frosh\Jetpack\Entity\Schema\EntitySchema;
use Frosh\Jetpack\Entity\Schema\FieldSchema;
use Frosh\Jetpack\Entity\Schema\IndexSchema;

final class SchemaDiffer
{
    public function diff(BundleSchema $previous, BundleSchema $current): MigrationPlan
    {
        $operations = [];
        $oldByTable = [];
        foreach ($previous->entities as $entity) {
            $oldByTable[$entity->entityName] = $entity;
        }
        $matchedOldTables = [];

        foreach ($current->entities as $entity) {
            $oldTable = $entity->entityName;
            $old = $oldByTable[$oldTable] ?? null;
            if (!$old instanceof EntitySchema && $entity->renamedFrom !== null) {
                $oldTable = $entity->renamedFrom;
                $old = $oldByTable[$oldTable] ?? null;
            }
            if (!$old instanceof EntitySchema) {
                $operations[] = new SchemaOperation(OperationType::CreateTable, $entity->entityName, false, entity: $entity);
                foreach ($entity->indexes as $index) {
                    $operations[] = new SchemaOperation(OperationType::AddIndex, $entity->entityName, false, entity: $entity, index: $index);
                }
                foreach ($entity->foreignKeys as $foreignKey) {
                    $operations[] = new SchemaOperation(OperationType::AddForeignKey, $entity->entityName, false, entity: $entity, foreignKey: $foreignKey);
                }
                continue;
            }

            $matchedOldTables[$old->entityName] = true;
            $renamed = $old->entityName !== $entity->entityName;
            if ($renamed) {
                $operations[] = new SchemaOperation(OperationType::RenameTable, $entity->entityName, true, entity: $entity, previousEntity: $old, previousTable: $old->entityName);
            }
            array_push($operations, ...$this->diffEntity($old, $entity, $renamed));
        }

        foreach ($previous->entities as $entity) {
            if (!isset($matchedOldTables[$entity->entityName])) {
                foreach ($entity->foreignKeys as $foreignKey) {
                    $operations[] = new SchemaOperation(OperationType::DropForeignKey, $entity->entityName, true, entity: $entity, previousForeignKey: $foreignKey);
                }
                $operations[] = new SchemaOperation(OperationType::DropTable, $entity->entityName, true, entity: $entity);
            }
        }

        $operations = $this->deferForeignKeysWhenPlanIsDestructive($operations);

        return new MigrationPlan(
            $this->sort($operations),
            $previous->fingerprint() !== $current->fingerprint(),
        );
    }

    /**
     * @return list<SchemaOperation>
     */
    private function diffEntity(EntitySchema $old, EntitySchema $new, bool $forceDestructive): array
    {
        $operations = [];
        $alteredColumns = [];
        $oldColumns = [];
        foreach ($old->columns() as $field) {
            $oldColumns[$field->storageName] = $field;
        }
        $matched = [];

        foreach ($new->columns() as $field) {
            $oldName = $field->storageName;
            $previousField = $oldColumns[$oldName] ?? null;
            if (!$previousField instanceof FieldSchema && $field->renamedFrom !== null) {
                $oldName = $field->renamedFrom;
                $previousField = $oldColumns[$oldName] ?? null;
            }
            if (!$previousField instanceof FieldSchema) {
                if (!$field->nullable && !$field->hasDefault) {
                    throw UnsafeSchemaChangeException::requiredColumnWithoutDefault($new->entityName, $field->storageName);
                }
                $operations[] = new SchemaOperation(OperationType::AddColumn, $new->entityName, $forceDestructive, entity: $new, field: $field);
                continue;
            }
            $matched[$previousField->storageName] = true;
            if ($previousField->storageName !== $field->storageName) {
                $operations[] = new SchemaOperation(OperationType::RenameColumn, $new->entityName, true, entity: $new, field: $field, previousField: $previousField);
            }
            if ($this->columnSignature($previousField) !== $this->columnSignature($field)) {
                $operations[] = new SchemaOperation(OperationType::AlterColumn, $new->entityName, true, entity: $new, field: $field, previousField: $previousField);
                $alteredColumns[$field->storageName] = true;
            }
        }

        foreach ($old->columns() as $field) {
            if (isset($matched[$field->storageName])) {
                continue;
            }
            $operations[] = new SchemaOperation(OperationType::DropColumn, $new->entityName, true, entity: $new, previousField: $field);
        }

        array_push($operations, ...$this->diffForeignKeys($old, $new, $forceDestructive, $alteredColumns));
        if ([$old->engine, $old->charset, $old->collation] !== [$new->engine, $new->charset, $new->collation]) {
            $operations[] = new SchemaOperation(OperationType::AlterTableOptions, $new->entityName, true, entity: $new, previousEntity: $old);
        }
        $oldPrimary = $this->primaryKey($old);
        $newPrimary = $this->primaryKey($new);
        if ($oldPrimary !== $newPrimary) {
            if ($oldPrimary !== []) {
                $operations[] = new SchemaOperation(OperationType::DropPrimaryKey, $new->entityName, true, entity: $new, previousEntity: $old);
            }
            if ($newPrimary !== []) {
                $operations[] = new SchemaOperation(OperationType::AddPrimaryKey, $new->entityName, true, entity: $new);
            }
        }
        array_push($operations, ...$this->diffIndexes($old, $new, $forceDestructive));

        return $operations;
    }

    /**
     * @return list<SchemaOperation>
     */
    private function diffIndexes(EntitySchema $old, EntitySchema $new, bool $forceDestructive): array
    {
        $operations = [];
        $oldIndexes = [];
        foreach ($old->indexes as $index) {
            $oldIndexes[$index->name] = $index;
        }
        $newIndexes = [];
        foreach ($new->indexes as $index) {
            $newIndexes[$index->name] = $index;
            $oldIndex = $oldIndexes[$index->name] ?? null;
            if (!$oldIndex instanceof IndexSchema) {
                $operations[] = new SchemaOperation(OperationType::AddIndex, $new->entityName, $forceDestructive, entity: $new, index: $index);
                continue;
            }
            if ($oldIndex->toArray() !== $index->toArray()) {
                $operations[] = new SchemaOperation(OperationType::DropIndex, $new->entityName, true, entity: $new, index: $oldIndex);
                $operations[] = new SchemaOperation(OperationType::AddIndex, $new->entityName, true, entity: $new, index: $index);
            }
        }
        foreach ($old->indexes as $index) {
            if (!isset($newIndexes[$index->name])) {
                $operations[] = new SchemaOperation(OperationType::DropIndex, $new->entityName, true, entity: $new, index: $index);
            }
        }

        return $operations;
    }

    private function columnSignature(FieldSchema $field): string
    {
        return json_encode([
            $field->type->value,
            $field->nullable,
            $field->length,
            $field->hasDefault,
            $field->default,
            $field->enumClass,
            $field->fieldFactory,
        ], \JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>
     */
    private function primaryKey(EntitySchema $entity): array
    {
        return array_values(array_map(
            static fn (FieldSchema $field): string => $field->storageName,
            array_filter($entity->columns(), static fn (FieldSchema $field): bool => $field->primaryKey),
        ));
    }

    /**
     * @param array<string, true> $alteredColumns
     *
     * @return list<SchemaOperation>
     */
    private function diffForeignKeys(EntitySchema $old, EntitySchema $new, bool $forceDestructive, array $alteredColumns): array
    {
        $operations = [];
        $oldForeignKeys = [];
        foreach ($old->foreignKeys as $foreignKey) {
            $oldForeignKeys[$foreignKey->name] = $foreignKey;
        }
        $newForeignKeys = [];
        foreach ($new->foreignKeys as $foreignKey) {
            $newForeignKeys[$foreignKey->name] = $foreignKey;
            $previous = $oldForeignKeys[$foreignKey->name] ?? null;
            if ($previous === null) {
                $operations[] = new SchemaOperation(OperationType::AddForeignKey, $new->entityName, $forceDestructive, entity: $new, foreignKey: $foreignKey);
                continue;
            }
            if ($previous->toArray() !== $foreignKey->toArray() || $this->usesAlteredColumn($foreignKey->localColumns, $alteredColumns)) {
                $operations[] = new SchemaOperation(OperationType::DropForeignKey, $new->entityName, true, entity: $new, previousForeignKey: $previous);
                $operations[] = new SchemaOperation(OperationType::AddForeignKey, $new->entityName, true, entity: $new, foreignKey: $foreignKey);
            }
        }
        foreach ($old->foreignKeys as $foreignKey) {
            if (!isset($newForeignKeys[$foreignKey->name])) {
                $operations[] = new SchemaOperation(OperationType::DropForeignKey, $new->entityName, true, entity: $new, previousForeignKey: $foreignKey);
            }
        }

        return $operations;
    }

    /**
     * @param list<string> $columns
     * @param array<string, true> $alteredColumns
     */
    private function usesAlteredColumn(array $columns, array $alteredColumns): bool
    {
        foreach ($columns as $column) {
            if (isset($alteredColumns[$column])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<SchemaOperation> $operations
     *
     * @return list<SchemaOperation>
     */
    private function sort(array $operations): array
    {
        $priorities = [
            OperationType::CreateTable->value => 10,
            OperationType::RenameTable->value => 20,
            OperationType::DropForeignKey->value => 30,
            OperationType::DropPrimaryKey->value => 35,
            OperationType::RenameColumn->value => 40,
            OperationType::AddColumn->value => 50,
            OperationType::AlterColumn->value => 60,
            OperationType::AlterTableOptions->value => 65,
            OperationType::DropIndex->value => 70,
            OperationType::DropColumn->value => 80,
            OperationType::AddIndex->value => 90,
            OperationType::AddPrimaryKey->value => 95,
            OperationType::AddForeignKey->value => 100,
            OperationType::DropTable->value => 110,
        ];
        usort($operations, static function (SchemaOperation $a, SchemaOperation $b) use ($priorities): int {
            $destructive = $a->destructive <=> $b->destructive;
            if ($destructive !== 0) {
                return $destructive;
            }
            $priority = $priorities[$a->type->value] <=> $priorities[$b->type->value];
            if ($priority !== 0) {
                return $priority;
            }

            return [$a->table, $a->description()] <=> [$b->table, $b->description()];
        });

        return $operations;
    }

    /**
     * @param list<SchemaOperation> $operations
     *
     * @return list<SchemaOperation>
     */
    private function deferForeignKeysWhenPlanIsDestructive(array $operations): array
    {
        $hasDestructiveOperation = false;
        foreach ($operations as $operation) {
            if ($operation->destructive) {
                $hasDestructiveOperation = true;
                break;
            }
        }
        if (!$hasDestructiveOperation) {
            return $operations;
        }

        return array_map(static function (SchemaOperation $operation): SchemaOperation {
            if ($operation->type !== OperationType::AddForeignKey || $operation->destructive) {
                return $operation;
            }

            return new SchemaOperation(
                type: $operation->type,
                table: $operation->table,
                destructive: true,
                entity: $operation->entity,
                previousEntity: $operation->previousEntity,
                field: $operation->field,
                previousField: $operation->previousField,
                index: $operation->index,
                previousTable: $operation->previousTable,
                foreignKey: $operation->foreignKey,
                previousForeignKey: $operation->previousForeignKey,
            );
        }, $operations);
    }
}
