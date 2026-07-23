<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Routing\Attribute;

use Frosh\Jetpack\Routing\Attribute\AdminApiRoute;
use Frosh\Jetpack\Routing\Attribute\ScopedRoute;
use Frosh\Jetpack\Routing\Attribute\StoreApiRoute;
use Frosh\Jetpack\Routing\Attribute\StorefrontRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Routing\Attribute\Route;

#[CoversClass(AdminApiRoute::class)]
#[CoversClass(ScopedRoute::class)]
#[CoversClass(StoreApiRoute::class)]
#[CoversClass(StorefrontRoute::class)]
final class ScopedRouteTest extends TestCase
{
    public function testStoreApiRouteIsLoadedBySymfonyAndForwardsTheSharedRouteArguments(): void
    {
        $routes = (new AttributeRouteControllerLoader('test'))->load(StoreApiInvokableController::class);
        $route = $routes->get('store-api.jetpack.example');

        static::assertNotNull($route);
        static::assertSame('/store-api/jetpack/{id}', $route->getPath());
        static::assertSame([StoreApiRouteScope::ID], $route->getDefault(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE));
        static::assertSame('value', $route->getDefault('custom'));
        static::assertSame('en-GB', $route->getDefault('_locale'));
        static::assertSame('json', $route->getDefault('_format'));
        static::assertTrue($route->getDefault('_stateless'));
        static::assertSame('\\d+', $route->getRequirement('id'));
        static::assertSame(['GET'], $route->getMethods());
        static::assertSame(['https'], $route->getSchemes());
        static::assertSame('example.test', $route->getHost());
        static::assertSame('request.getMethod() == \'GET\'', $route->getCondition());
        static::assertTrue($route->getOption('utf8'));
        static::assertSame('option-value', $route->getOption('custom-option'));
        static::assertSame(42, $routes->getPriority('store-api.jetpack.example'));
    }

    public function testAdminApiRouteCanBeUsedDirectlyOnAMethod(): void
    {
        $routes = (new AttributeRouteControllerLoader())->load(AdminApiController::class);
        $route = $routes->get('api.action.jetpack.example');

        static::assertNotNull($route);
        static::assertSame('/api/_action/jetpack/example', $route->getPath());
        static::assertSame([ApiRouteScope::ID], $route->getDefault(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE));
        static::assertSame(['POST'], $route->getMethods());
    }

    public function testStorefrontRouteCanProvideClassDefaultsForRegularSymfonyRoutes(): void
    {
        $routes = (new AttributeRouteControllerLoader())->load(StorefrontController::class);
        $route = $routes->get('frontend.jetpack.example');

        static::assertNotNull($route);
        static::assertSame('/jetpack/example', $route->getPath());
        static::assertSame([StorefrontRouteScope::ID], $route->getDefault(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE));
        static::assertSame('class-value', $route->getDefault('class-default'));
        static::assertSame('method-value', $route->getDefault('method-default'));
    }

    public function testScopedRoutesRemainRepeatable(): void
    {
        $routes = (new AttributeRouteControllerLoader())->load(RepeatedStoreApiController::class);

        static::assertCount(2, $routes);
        static::assertSame(
            [StoreApiRouteScope::ID],
            $routes->get('store-api.jetpack.first')?->getDefault(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE),
        );
        static::assertSame(
            [StoreApiRouteScope::ID],
            $routes->get('store-api.jetpack.second')?->getDefault(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE),
        );
    }

    public function testRejectsAManualRouteScopeOverride(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException(\sprintf(
            'The defaults of "%s" must not define "%s"; use Symfony Route for custom or multiple scopes.',
            StoreApiRoute::class,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE,
        )));

        new StoreApiRoute(defaults: [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID],
        ]);
    }

    public function testEveryShortcutSupportsClassesMethodsAndRepeatedRoutes(): void
    {
        $expectedFlags = \Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD;

        foreach ([AdminApiRoute::class, StoreApiRoute::class, StorefrontRoute::class] as $class) {
            $attributes = (new \ReflectionClass($class))->getAttributes(\Attribute::class);
            static::assertCount(1, $attributes);
            static::assertSame($expectedFlags, $attributes[0]->newInstance()->flags);
        }
    }
}

#[StoreApiRoute(
    path: '/store-api/jetpack/{id}',
    name: 'store-api.jetpack.example',
    requirements: ['id' => '\\d+'],
    options: ['custom-option' => 'option-value'],
    defaults: ['custom' => 'value'],
    host: 'example.test',
    methods: ['GET'],
    schemes: ['https'],
    condition: 'request.getMethod() == \'GET\'',
    priority: 42,
    locale: 'en-GB',
    format: 'json',
    utf8: true,
    stateless: true,
    env: 'test',
)]
final class StoreApiInvokableController
{
    public function __invoke(): void
    {
    }
}

final class AdminApiController
{
    #[AdminApiRoute(path: '/api/_action/jetpack/example', name: 'api.action.jetpack.example', methods: ['POST'])]
    public function example(): void
    {
    }
}

#[StorefrontRoute(defaults: ['class-default' => 'class-value'])]
final class StorefrontController
{
    #[Route(path: '/jetpack/example', name: 'frontend.jetpack.example', defaults: ['method-default' => 'method-value'])]
    public function example(): void
    {
    }
}

final class RepeatedStoreApiController
{
    #[StoreApiRoute(path: '/store-api/jetpack/first', name: 'store-api.jetpack.first')]
    #[StoreApiRoute(path: '/store-api/jetpack/second', name: 'store-api.jetpack.second')]
    public function example(): void
    {
    }
}
