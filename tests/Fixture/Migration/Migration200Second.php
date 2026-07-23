<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture\Migration;

use Doctrine\DBAL\Connection;
use Frosh\Jetpack\Migration\Migration;

final class Migration200Second extends Migration
{
    public function getCreationTimestamp(): int
    {
        return 200;
    }

    public function up(Connection $connection): void
    {
        MigrationExecutionLog::$entries[] = 'up:second';
    }

    public function down(Connection $connection): void
    {
        MigrationExecutionLog::$entries[] = 'down:second';
    }
}
