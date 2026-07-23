<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture;

use Shopware\Core\Framework\Bundle;

final class InvalidCustomFieldSchemaBundle extends Bundle
{
    public function getPath(): string
    {
        return __DIR__ . '/InvalidCustomFieldSchemaBundle';
    }
}
