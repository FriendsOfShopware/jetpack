<?php declare(strict_types=1);

namespace Frosh\Jetpack\Routing\Attribute;

use Shopware\Core\Framework\Routing\StoreApiRouteScope;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class StoreApiRoute extends ScopedRoute
{
    protected static function scope(): string
    {
        return StoreApiRouteScope::ID;
    }
}
