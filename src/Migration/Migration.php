<?php declare(strict_types=1);

namespace Frosh\Jetpack\Migration;

use Doctrine\DBAL\Connection;

abstract class Migration
{
    abstract public function getCreationTimestamp(): int;

    abstract public function up(Connection $connection): void;

    abstract public function down(Connection $connection): void;
}
