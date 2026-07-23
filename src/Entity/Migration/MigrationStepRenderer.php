<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Migration;

use Frosh\Jetpack\Entity\Schema\BundleSchema;

/**
 * @internal
 */
final class MigrationStepRenderer
{
    public function __construct(private readonly MySqlSchemaRenderer $sqlRenderer)
    {
    }

    public function render(
        string $namespace,
        int $timestamp,
        string $name,
        MigrationPlan $up,
        MigrationPlan $down,
        BundleSchema $currentSchema,
        BundleSchema $previousSchema,
    ): RenderedMigration {
        $className = 'Migration' . $timestamp . $this->pascalCase($name);
        $upOperations = $this->renderOperations($up->operations, $currentSchema);
        $downOperations = $this->renderOperations(
            $down->operations,
            $this->downReferenceSchema($currentSchema, $previousSchema),
        );
        $content = \sprintf(
            <<<'PHP'
<?php declare(strict_types=1);

namespace %s;

use Doctrine\DBAL\Connection;
use Frosh\Jetpack\Migration\Migration;

final class %s extends Migration
{
    public function getCreationTimestamp(): int
    {
        return %d;
    }

    public function up(Connection $connection): void
    {
%s
    }

    public function down(Connection $connection): void
    {
%s
    }

    private function tableExists(Connection $connection, string $table): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT COUNT(*) FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table',
            ['table' => $table],
        );
    }

    private function columnExists(Connection $connection, string $table, string $column): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table AND `COLUMN_NAME` = :column',
            ['table' => $table, 'column' => $column],
        );
    }

    private function indexExists(Connection $connection, string $table, string $index): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT COUNT(*) FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table AND `INDEX_NAME` = :index',
            ['table' => $table, 'index' => $index],
        );
    }

    private function foreignKeyExists(Connection $connection, string $table, string $foreignKey): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT COUNT(*) FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `CONSTRAINT_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table AND `CONSTRAINT_NAME` = :foreignKey AND `CONSTRAINT_TYPE` = \'FOREIGN KEY\'',
            ['table' => $table, 'foreignKey' => $foreignKey],
        );
    }
}

PHP,
            trim($namespace, '\\'),
            $className,
            $timestamp,
            $upOperations,
            $downOperations,
        );

        return new RenderedMigration($className, $content);
    }

    /**
     * @param list<SchemaOperation> $operations
     */
    private function renderOperations(array $operations, BundleSchema $schema): string
    {
        if ($operations === []) {
            return '        // No schema changes.';
        }

        return implode("\n\n", array_map(fn (SchemaOperation $operation): string => $this->renderOperation($operation, $schema), $operations));
    }

    private function renderOperation(SchemaOperation $operation, BundleSchema $schema): string
    {
        $sql = $this->sqlStatement($this->sqlRenderer->sql($operation, $schema));
        $table = var_export($operation->table, true);

        return match ($operation->type) {
            OperationType::CreateTable => '        ' . $sql,
            OperationType::DropTable => \sprintf("        if (\$this->tableExists(\$connection, %s)) {\n            %s\n        }", $table, $sql),
            OperationType::RenameTable => \sprintf(
                "        if (\$this->tableExists(\$connection, %s) && !\$this->tableExists(\$connection, %s)) {\n            %s\n        }",
                var_export($operation->previousTable, true),
                $table,
                $sql,
            ),
            OperationType::AddColumn => \sprintf(
                "        if (\$this->tableExists(\$connection, %s) && !\$this->columnExists(\$connection, %s, %s)) {\n            %s\n        }",
                $table,
                $table,
                var_export($operation->field?->storageName, true),
                $sql,
            ),
            OperationType::DropColumn => \sprintf(
                "        if (\$this->columnExists(\$connection, %s, %s)) {\n            %s\n        }",
                $table,
                var_export($operation->previousField?->storageName, true),
                $sql,
            ),
            OperationType::RenameColumn => \sprintf(
                "        if (\$this->columnExists(\$connection, %s, %s) && !\$this->columnExists(\$connection, %s, %s)) {\n            %s\n        }",
                $table,
                var_export($operation->previousField?->storageName, true),
                $table,
                var_export($operation->field?->storageName, true),
                $sql,
            ),
            OperationType::AlterColumn => \sprintf(
                "        if (\$this->columnExists(\$connection, %s, %s)) {\n            %s\n        }",
                $table,
                var_export($operation->field?->storageName, true),
                $sql,
            ),
            OperationType::AlterTableOptions => \sprintf(
                "        if (\$this->tableExists(\$connection, %s)) {\n            %s\n        }",
                $table,
                $sql,
            ),
            OperationType::AddIndex => \sprintf(
                "        if (\$this->tableExists(\$connection, %s) && !\$this->indexExists(\$connection, %s, %s)) {\n            %s\n        }",
                $table,
                $table,
                var_export($operation->index?->name, true),
                $sql,
            ),
            OperationType::DropIndex => \sprintf(
                "        if (\$this->indexExists(\$connection, %s, %s)) {\n            %s\n        }",
                $table,
                var_export($operation->index?->name, true),
                $sql,
            ),
            OperationType::AddForeignKey => \sprintf(
                "        if (\$this->tableExists(\$connection, %s) && !\$this->foreignKeyExists(\$connection, %s, %s)) {\n            %s\n        }",
                $table,
                $table,
                var_export(($operation->foreignKey ?? throw new \LogicException('Foreign-key operation needs a constraint.'))->name, true),
                $sql,
            ),
            OperationType::DropForeignKey => \sprintf(
                "        if (\$this->foreignKeyExists(\$connection, %s, %s)) {\n            %s\n        }",
                $table,
                var_export(($operation->previousForeignKey ?? throw new \LogicException('Foreign-key operation needs a previous constraint.'))->name, true),
                $sql,
            ),
            OperationType::AddPrimaryKey => \sprintf(
                "        if (\$this->tableExists(\$connection, %s) && !\$this->indexExists(\$connection, %s, 'PRIMARY')) {\n            %s\n        }",
                $table,
                $table,
                $sql,
            ),
            OperationType::DropPrimaryKey => \sprintf(
                "        if (\$this->indexExists(\$connection, %s, 'PRIMARY')) {\n            %s\n        }",
                $table,
                $sql,
            ),
        };
    }

    private function sqlStatement(string $sql): string
    {
        return "\$connection->executeStatement(<<<'SQL'\n" . $sql . "\nSQL\n        );";
    }

    private function pascalCase(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9]+/', ' ', $name) ?? $name;
        $name = str_replace(' ', '', ucwords(trim($name)));

        return $name === '' ? 'EntitySchema' : $name;
    }

    private function downReferenceSchema(BundleSchema $current, BundleSchema $previous): BundleSchema
    {
        $entities = $current->entities;
        $definitions = [];
        foreach ($current->entities as $entity) {
            $definitions[$entity->definitionClass] = true;
        }
        foreach ($previous->entities as $entity) {
            if (!isset($definitions[$entity->definitionClass])) {
                $entities[] = $entity;
            }
        }

        return new BundleSchema($current->bundleName, $entities);
    }
}
