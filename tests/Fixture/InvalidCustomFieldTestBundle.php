<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture;

use Shopware\Core\Framework\Bundle;

final class InvalidCustomFieldTestBundle extends Bundle
{
    public function getPath(): string
    {
        return __DIR__ . '/InvalidCustomFieldTestBundle';
    }
}
