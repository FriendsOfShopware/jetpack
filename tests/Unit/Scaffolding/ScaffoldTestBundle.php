<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Scaffolding;

use Shopware\Core\Framework\Bundle;

final class ScaffoldTestBundle extends Bundle
{
    public function __construct(private readonly string $bundlePath)
    {
    }

    public function getPath(): string
    {
        return $this->bundlePath;
    }

    public function getNamespace(): string
    {
        return 'Acme\\Review';
    }
}
