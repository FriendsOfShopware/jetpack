<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture\Migration;

use Doctrine\DBAL\Connection;
use Frosh\Jetpack\Migration\Migration;

final class Migration100First extends Migration
{
    public function getCreationTimestamp(): int
    {
        return 100;
    }

    public function up(Connection $connection): void
    {
        MigrationExecutionLog::$entries[] = 'up:first';
    }

    public function down(Connection $connection): void
    {
        MigrationExecutionLog::$entries[] = 'down:first';
    }
}
