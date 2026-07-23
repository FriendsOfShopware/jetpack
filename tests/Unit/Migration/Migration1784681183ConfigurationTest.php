<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Migration;

use Frosh\Jetpack\Migration\Migration1784681183Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Migration1784681183Configuration::class)]
final class Migration1784681183ConfigurationTest extends TestCase
{
    public function testIsReversibleJetpackMigration(): void
    {
        $migration = new Migration1784681183Configuration();

        static::assertSame(1784681183, $migration->getCreationTimestamp());
    }
}
