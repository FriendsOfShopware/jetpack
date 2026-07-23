<?php declare(strict_types=1);

namespace Frosh\Jetpack\Routing\Attribute;

use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class StorefrontRoute extends ScopedRoute
{
    protected static function scope(): string
    {
        return StorefrontRouteScope::ID;
    }
}
