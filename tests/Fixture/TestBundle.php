<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class TestBundle extends Bundle
{
    public function getPath(): string
    {
        return __DIR__ . '/TestBundle';
    }
}
