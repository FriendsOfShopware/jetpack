<?php declare(strict_types=1);

namespace Frosh\Jetpack\Migration;

use Doctrine\DBAL\Connection;

/**
 * @internal
 */
final class DbalMigrationLock implements MigrationLock
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function synchronized(string $bundle, \Closure $callback): mixed
    {
        $name = 'fj:' . hash('sha1', $bundle);
        $acquired = $this->connection->fetchOne(
            'SELECT GET_LOCK(:name, 60)',
            ['name' => $name],
        );
        if ($acquired !== 1 && $acquired !== '1' && $acquired !== true) {
            throw MigrationException::lockNotAcquired($bundle);
        }

        try {
            return $callback();
        } finally {
            $this->connection->fetchOne('SELECT RELEASE_LOCK(:name)', ['name' => $name]);
        }
    }
}
