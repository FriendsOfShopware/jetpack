<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture;

use Shopware\Core\Framework\Bundle;

final class MailTemplateTestBundle extends Bundle
{
    public function getPath(): string
    {
        return __DIR__ . '/MailTemplateTestBundle';
    }
}
