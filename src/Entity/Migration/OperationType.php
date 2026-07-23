<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Migration;

enum OperationType: string
{
    case CreateTable = 'create-table';
    case DropTable = 'drop-table';
    case RenameTable = 'rename-table';
    case AddColumn = 'add-column';
    case DropColumn = 'drop-column';
    case RenameColumn = 'rename-column';
    case AlterColumn = 'alter-column';
    case AlterTableOptions = 'alter-table-options';
    case AddIndex = 'add-index';
    case DropIndex = 'drop-index';
    case AddForeignKey = 'add-foreign-key';
    case DropForeignKey = 'drop-foreign-key';
    case AddPrimaryKey = 'add-primary-key';
    case DropPrimaryKey = 'drop-primary-key';
}
