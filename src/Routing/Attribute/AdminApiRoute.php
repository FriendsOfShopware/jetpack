<?php declare(strict_types=1);

namespace Frosh\Jetpack\Routing\Attribute;

use Shopware\Core\Framework\Routing\ApiRouteScope;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class AdminApiRoute extends ScopedRoute
{
    protected static function scope(): string
    {
        return ApiRouteScope::ID;
    }
}
