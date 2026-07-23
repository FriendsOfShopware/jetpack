<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\ScheduledTask;

use Frosh\Jetpack\ScheduledTask\ScheduledTaskDescriptor;
use Frosh\Jetpack\ScheduledTask\ScheduledTaskRegistry;
use Frosh\Jetpack\Tests\Fixture\CustomFieldTestBundle;
use Frosh\Jetpack\Tests\Fixture\MailTemplateTestBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScheduledTaskRegistry::class)]
final class ScheduledTaskRegistryTest extends TestCase
{
    public function testReturnsSortedDescriptorsAndFiltersThemByOwningBundle(): void
    {
        $customFieldBundle = new CustomFieldTestBundle();
        $mailTemplateBundle = new MailTemplateTestBundle();
        $customFieldTask = $this->descriptor(
            'acme.z_task',
            $customFieldBundle->getPath() . '/Resources/config/jetpack.yaml',
        );
        $mailTemplateTask = $this->descriptor(
            'acme.a_task',
            $mailTemplateBundle->getPath() . '/Resources/config/mail-templates.yaml',
        );
        $registry = new ScheduledTaskRegistry([$customFieldTask, $mailTemplateTask]);

        static::assertSame([$mailTemplateTask, $customFieldTask], $registry->all());
        static::assertSame([$customFieldTask], $registry->forBundle($customFieldBundle));
        static::assertSame([$mailTemplateTask], $registry->forBundle($mailTemplateBundle));
    }

    private function descriptor(string $name, string $sourceFile): ScheduledTaskDescriptor
    {
        return new ScheduledTaskDescriptor(
            self::class . '.' . $name,
            self::class,
            $name,
            300,
            false,
            self::class,
            $sourceFile,
        );
    }
}
