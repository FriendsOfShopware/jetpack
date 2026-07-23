<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture;

use Shopware\Core\Framework\Bundle;

final class MigrationTestBundle extends Bundle
{
    public function getMigrationNamespace(): string
    {
        return 'Frosh\\Jetpack\\Tests\\Fixture\\Migration';
    }

    public function getMigrationPath(): string
    {
        return __DIR__ . '/Migration';
    }
}
