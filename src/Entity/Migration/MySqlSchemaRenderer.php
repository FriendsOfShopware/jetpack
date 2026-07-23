<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Migration;

use Frosh\Jetpack\Attribute\FieldType;
use Frosh\Jetpack\Entity\Field\FieldFactory;
use Frosh\Jetpack\Entity\Schema\BundleSchema;
use Frosh\Jetpack\Entity\Schema\EntitySchema;
use Frosh\Jetpack\Entity\Schema\FieldSchema;
use Frosh\Jetpack\Entity\Schema\ForeignKeyConstraintSchema;
use Frosh\Jetpack\Entity\Schema\IndexSchema;

/**
 * @internal
 */
final class MySqlSchemaRenderer
{
    public function __construct(private readonly DefinitionNameResolver $definitionNameResolver)
    {
    }

    public function sql(SchemaOperation $operation, BundleSchema $schema): string
    {
        return match ($operation->type) {
            OperationType::CreateTable => $this->createTable($operation->entity ?? throw new \LogicException('Create-table operation needs an entity.')),
            OperationType::DropTable => \sprintf('DROP TABLE IF EXISTS %s', $this->identifier($operation->table)),
            OperationType::RenameTable => \sprintf('ALTER TABLE %s RENAME TO %s', $this->identifier($operation->previousTable ?? ''), $this->identifier($operation->table)),
            OperationType::AddColumn => \sprintf('ALTER TABLE %s ADD COLUMN %s', $this->identifier($operation->table), $this->column($operation->field ?? throw new \LogicException('Add-column operation needs a field.'))),
            OperationType::DropColumn => \sprintf('ALTER TABLE %s DROP COLUMN %s', $this->identifier($operation->table), $this->identifier(($operation->previousField ?? throw new \LogicException('Drop-column operation needs a previous field.'))->storageName)),
            OperationType::RenameColumn => \sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $this->identifier($operation->table),
                $this->identifier(($operation->previousField ?? throw new \LogicException('Rename-column operation needs a previous field.'))->storageName),
                $this->identifier(($operation->field ?? throw new \LogicException('Rename-column operation needs a field.'))->storageName),
            ),
            OperationType::AlterColumn => \sprintf('ALTER TABLE %s MODIFY COLUMN %s', $this->identifier($operation->table), $this->column($operation->field ?? throw new \LogicException('Alter-column operation needs a field.'))),
            OperationType::AlterTableOptions => $this->alterTableOptions($operation->table, $operation->entity ?? throw new \LogicException('Alter-table-options operation needs an entity.')),
            OperationType::AddIndex => $this->addIndex($operation->table, $operation->index ?? throw new \LogicException('Add-index operation needs an index.')),
            OperationType::DropIndex => \sprintf('ALTER TABLE %s DROP INDEX %s', $this->identifier($operation->table), $this->identifier(($operation->index ?? throw new \LogicException('Drop-index operation needs an index.'))->name)),
            OperationType::AddForeignKey => $this->addForeignKey($operation->table, $operation->foreignKey ?? throw new \LogicException('Add-foreign-key operation needs a constraint.'), $schema),
            OperationType::DropForeignKey => \sprintf(
                'ALTER TABLE %s DROP FOREIGN KEY %s',
                $this->identifier($operation->table),
                $this->identifier(($operation->previousForeignKey ?? throw new \LogicException('Drop-foreign-key operation needs a previous constraint.'))->name),
            ),
            OperationType::AddPrimaryKey => \sprintf(
                'ALTER TABLE %s ADD PRIMARY KEY (%s)',
                $this->identifier($operation->table),
                implode(', ', array_map(
                    fn (FieldSchema $field): string => $this->identifier($field->storageName),
                    array_filter(($operation->entity ?? throw new \LogicException('Add-primary-key operation needs an entity.'))->columns(), static fn (FieldSchema $field): bool => $field->primaryKey),
                )),
            ),
            OperationType::DropPrimaryKey => \sprintf('ALTER TABLE %s DROP PRIMARY KEY', $this->identifier($operation->table)),
        };
    }

    private function createTable(EntitySchema $entity): string
    {
        $columns = array_map($this->column(...), $entity->columns());
        $primary = array_values(array_map(
            fn (FieldSchema $field): string => $this->identifier($field->storageName),
            array_filter($entity->columns(), static fn (FieldSchema $field): bool => $field->primaryKey),
        ));
        if ($primary !== []) {
            $columns[] = 'PRIMARY KEY (' . implode(', ', $primary) . ')';
        }

        return \sprintf(
            "CREATE TABLE IF NOT EXISTS %s (\n    %s\n) ENGINE=%s DEFAULT CHARSET=%s COLLATE=%s",
            $this->identifier($entity->entityName),
            implode(",\n    ", $columns),
            $this->plainIdentifier($entity->engine),
            $this->plainIdentifier($entity->charset),
            $this->plainIdentifier($entity->collation),
        );
    }

    private function alterTableOptions(string $table, EntitySchema $entity): string
    {
        return \sprintf(
            'ALTER TABLE %s ENGINE=%s DEFAULT CHARSET=%s COLLATE=%s',
            $this->identifier($table),
            $this->plainIdentifier($entity->engine),
            $this->plainIdentifier($entity->charset),
            $this->plainIdentifier($entity->collation),
        );
    }

    private function addIndex(string $table, IndexSchema $index): string
    {
        return \sprintf(
            'ALTER TABLE %s ADD %sINDEX %s (%s)',
            $this->identifier($table),
            $index->unique ? 'UNIQUE ' : '',
            $this->identifier($index->name),
            implode(', ', array_map($this->identifier(...), $index->columns)),
        );
    }

    private function addForeignKey(string $table, ForeignKeyConstraintSchema $foreignKey, BundleSchema $schema): string
    {
        $target = $schema->entityByDefinition($foreignKey->targetDefinition);
        $targetTable = $target === null ? $this->definitionNameResolver->entityName($foreignKey->targetDefinition) : $target->entityName;
        $targetColumns = array_map(
            fn (string $field): string => $this->identifier($target === null ? $this->snakeCase($field) : $target->field($field)->storageName),
            $foreignKey->referenceFields,
        );

        return \sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s ON UPDATE CASCADE',
            $this->identifier($table),
            $this->identifier($foreignKey->name),
            implode(', ', array_map($this->identifier(...), $foreignKey->localColumns)),
            $this->identifier($targetTable),
            implode(', ', $targetColumns),
            $foreignKey->onDelete->value,
        );
    }

    private function column(FieldSchema $field): string
    {
        $sql = $this->identifier($field->storageName) . ' ' . $this->columnType($field);
        $sql .= $field->nullable ? ' NULL' : ' NOT NULL';
        if ($field->hasDefault) {
            $sql .= ' DEFAULT ' . $this->defaultValue($field->default);
        }

        return $sql;
    }

    private function columnType(FieldSchema $field): string
    {
        if ($field->sqlType !== null) {
            return $this->validatedSqlType($field->sqlType, $field->propertyName);
        }

        return match ($field->type) {
            FieldType::Uuid, FieldType::Version, FieldType::ReferenceVersion => 'BINARY(16)',
            FieldType::String, FieldType::Email => 'VARCHAR(' . ($field->length ?? 255) . ')',
            FieldType::Text => 'LONGTEXT',
            FieldType::Int => 'INT',
            FieldType::Float => 'DOUBLE',
            FieldType::Bool => 'TINYINT(1)',
            FieldType::DateTime => 'DATETIME(3)',
            FieldType::Date => 'DATE',
            FieldType::Json, FieldType::Price, FieldType::CustomFields => 'JSON',
            FieldType::Blob => 'LONGBLOB',
            FieldType::Enum => $this->enumColumnType($field),
            FieldType::Custom => $this->customColumnType($field),
        };
    }

    private function enumColumnType(FieldSchema $field): string
    {
        $class = $field->enumClass;
        if ($class === null || !enum_exists($class)) {
            throw new \InvalidArgumentException(\sprintf('Enum field "%s" has no valid enum class.', $field->propertyName));
        }
        $type = (new \ReflectionEnum($class))->getBackingType()?->getName();

        return $type === 'int' ? 'INT' : 'VARCHAR(' . ($field->length ?? 255) . ')';
    }

    private function customColumnType(FieldSchema $field): string
    {
        $factoryClass = $field->fieldFactory;
        if ($factoryClass === null || !is_a($factoryClass, FieldFactory::class, true)) {
            throw new \InvalidArgumentException(\sprintf('Custom field "%s" has no valid field factory.', $field->propertyName));
        }
        $sqlType = trim((new $factoryClass())->sqlType($field));

        return $this->validatedSqlType($sqlType, $factoryClass);
    }

    private function validatedSqlType(string $sqlType, string $source): string
    {
        $sqlType = trim($sqlType);
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:\(\s*[1-9][0-9]*(?:\s*,\s*[0-9]+)?\s*\))?(?:\s+(?:UNSIGNED|ZEROFILL))*$/i', $sqlType) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Field SQL type source "%s" returned an unsafe SQL type.', $source));
        }

        return $sqlType;
    }

    private function defaultValue(string|int|float|bool|null $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            \is_bool($value) => $value ? '1' : '0',
            \is_int($value), \is_float($value) => (string) $value,
            default => '\'' . str_replace(['\\', '\''], ['\\\\', '\'\''], $value) . '\'',
        };
    }

    private function identifier(string $identifier): string
    {
        return '`' . $this->plainIdentifier($identifier) . '`';
    }

    private function plainIdentifier(string $identifier): string
    {
        if ($identifier === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Unsafe SQL identifier "%s".', $identifier));
        }

        return $identifier;
    }

    private function snakeCase(string $name): string
    {
        $normalized = preg_replace('/(?<!^)[A-Z]/', '_$0', $name);

        return strtolower($normalized ?? $name);
    }
}
