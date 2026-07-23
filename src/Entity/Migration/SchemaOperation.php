<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Migration;

use Frosh\Jetpack\Entity\Schema\EntitySchema;
use Frosh\Jetpack\Entity\Schema\FieldSchema;
use Frosh\Jetpack\Entity\Schema\ForeignKeyConstraintSchema;
use Frosh\Jetpack\Entity\Schema\IndexSchema;

final readonly class SchemaOperation
{
    public function __construct(
        public OperationType $type,
        public string $table,
        public bool $destructive,
        public ?EntitySchema $entity = null,
        public ?EntitySchema $previousEntity = null,
        public ?FieldSchema $field = null,
        public ?FieldSchema $previousField = null,
        public ?IndexSchema $index = null,
        public ?string $previousTable = null,
        public ?ForeignKeyConstraintSchema $foreignKey = null,
        public ?ForeignKeyConstraintSchema $previousForeignKey = null,
    ) {
    }

    public function description(): string
    {
        return match ($this->type) {
            OperationType::CreateTable => \sprintf('create table %s', $this->table),
            OperationType::DropTable => \sprintf('drop table %s', $this->table),
            OperationType::RenameTable => \sprintf('rename table %s to %s', $this->previousTable, $this->table),
            OperationType::AddColumn => \sprintf('add column %s.%s', $this->table, $this->field?->storageName),
            OperationType::DropColumn => \sprintf('drop column %s.%s', $this->table, $this->previousField?->storageName),
            OperationType::RenameColumn => \sprintf('rename column %s.%s to %s', $this->table, $this->previousField?->storageName, $this->field?->storageName),
            OperationType::AlterColumn => \sprintf('alter column %s.%s', $this->table, $this->field?->storageName),
            OperationType::AlterTableOptions => \sprintf('alter table options on %s', $this->table),
            OperationType::AddIndex => \sprintf('add index %s on %s', $this->index?->name, $this->table),
            OperationType::DropIndex => \sprintf('drop index %s on %s', $this->index?->name, $this->table),
            OperationType::AddForeignKey => \sprintf('add foreign key %s on %s', $this->foreignKey?->name, $this->table),
            OperationType::DropForeignKey => \sprintf('drop foreign key %s on %s', $this->previousForeignKey?->name, $this->table),
            OperationType::AddPrimaryKey => \sprintf('add primary key on %s', $this->table),
            OperationType::DropPrimaryKey => \sprintf('drop primary key on %s', $this->table),
        };
    }
}
