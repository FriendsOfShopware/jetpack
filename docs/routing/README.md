# Scoped route attributes

Frosh Jetpack provides native Symfony route subclasses for Shopware's three common HTTP surfaces:

- `StorefrontRoute` selects Shopware's storefront route scope;
- `StoreApiRoute` selects Shopware's Store API route scope;
- `AdminApiRoute` selects Shopware's Administration API route scope.

The attributes replace the repetitive `_routeScope` default and its supporting imports. They are
loaded directly by Symfony's attribute route loader; no Jetpack compiler pass, controller base class,
or runtime listener is involved.

## Direct method routes

```php
<?php declare(strict_types=1);

namespace Acme\Review\StoreApi;

use Frosh\Jetpack\Routing\Attribute\StoreApiRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final readonly class ReviewController
{
    #[StoreApiRoute(
        path: '/store-api/acme/reviews',
        name: 'store-api.acme.reviews',
        methods: [Request::METHOD_GET],
    )]
    public function reviews(SalesChannelContext $context): JsonResponse
    {
        return new JsonResponse([]);
    }
}
```

Equivalent native Symfony configuration would be:

```php
#[Route(
    path: '/store-api/acme/reviews',
    name: 'store-api.acme.reviews',
    methods: [Request::METHOD_GET],
    defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]],
)]
```

`AdminApiRoute` and `StorefrontRoute` have the same constructor and usage:

```php
#[AdminApiRoute(path: '/api/_action/acme/rebuild', name: 'api.action.acme.rebuild', methods: ['POST'])]

#[StorefrontRoute(path: '/account/acme', name: 'frontend.account.acme', methods: ['GET'])]
```

The class names deliberately follow Shopware's `Api` naming style rather than spelling the acronym as
`API`.

## Class-level scope

All three attributes support classes and methods. Put the scoped attribute on a controller class when
several regular Symfony routes share one scope:

```php
use Frosh\Jetpack\Routing\Attribute\StorefrontRoute;
use Symfony\Component\Routing\Attribute\Route;

#[StorefrontRoute]
final class AccountController
{
    #[Route(path: '/account/acme', name: 'frontend.account.acme', methods: ['GET'])]
    public function overview(): Response
    {
        // ...
    }

    #[Route(path: '/account/acme/save', name: 'frontend.account.acme.save', methods: ['POST'])]
    public function save(): Response
    {
        // ...
    }
}
```

Scoped attributes remain repeatable, so one method can declare several routes of the same scope.

## Supported Symfony arguments

The shortcuts expose the constructor arguments shared by the Symfony versions used in Shopware 6.6
and 6.7:

- `path`, `name`, `requirements`, `options`, and `defaults`;
- `host`, `methods`, `schemes`, `condition`, and `priority`;
- `locale`, `format`, `utf8`, `stateless`, and a single `env`.

Symfony 7.4 route aliases and multiple environments are not exposed because they are unavailable in
the Symfony version used by Shopware 6.6. Use Symfony's regular `Route` attribute when either feature
is required.

## Deliberate boundaries

The shortcut adds exactly one default:

```php
PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]
```

Caller-provided defaults are preserved, but manually providing `_routeScope` is rejected. Use a
regular Symfony `Route` for custom or multiple scopes.

Jetpack does not:

- prepend `/api` or `/store-api` to paths;
- generate route names;
- change `auth_required`, login, maintenance, cache, or stateless behavior;
- register the controller as a service or import its directory into Symfony routing;
- generate OpenAPI schemas for public Admin API or Store API endpoints.

Consumer plugins therefore keep their normal service registration and attribute route import. The
route path and name remain explicit, making a route's external contract visible at its declaration.

## Public API

The supported API consists of:

- `Frosh\Jetpack\Routing\Attribute\AdminApiRoute`;
- `Frosh\Jetpack\Routing\Attribute\StoreApiRoute`;
- `Frosh\Jetpack\Routing\Attribute\StorefrontRoute`;
- their documented constructor arguments and fixed Shopware scope mappings.

The shared `ScopedRoute` base class is internal and must not be referenced by consumers.
