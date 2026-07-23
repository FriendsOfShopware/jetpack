<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Migration;

use Frosh\Jetpack\Migration\Migration1784714527MailTemplateOwnership;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Migration1784714527MailTemplateOwnership::class)]
final class Migration1784714527MailTemplateOwnershipTest extends TestCase
{
    public function testIsReversibleJetpackMigration(): void
    {
        $migration = new Migration1784714527MailTemplateOwnership();

        static::assertSame(1784714527, $migration->getCreationTimestamp());
    }
}
