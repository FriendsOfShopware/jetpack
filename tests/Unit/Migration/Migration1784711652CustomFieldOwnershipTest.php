<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Migration;

use Frosh\Jetpack\Migration\Migration1784711652CustomFieldOwnership;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Migration1784711652CustomFieldOwnership::class)]
final class Migration1784711652CustomFieldOwnershipTest extends TestCase
{
    public function testIsReversibleJetpackMigration(): void
    {
        $migration = new Migration1784711652CustomFieldOwnership();

        static::assertSame(1784711652, $migration->getCreationTimestamp());
    }
}
