<?php declare(strict_types=1);

namespace Frosh\Jetpack\Routing\Attribute;

use Shopware\Core\PlatformRequest;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
abstract class ScopedRoute extends Route
{
    /**
     * @param string|array<string, string>|null $path
     * @param array<string, string|\Stringable> $requirements
     * @param array<string, mixed> $options
     * @param array<string, mixed> $defaults
     * @param string|list<string> $methods
     * @param string|list<string> $schemes
     */
    final public function __construct(
        string|array|null $path = null,
        ?string $name = null,
        array $requirements = [],
        array $options = [],
        array $defaults = [],
        ?string $host = null,
        array|string $methods = [],
        array|string $schemes = [],
        ?string $condition = null,
        ?int $priority = null,
        ?string $locale = null,
        ?string $format = null,
        ?bool $utf8 = null,
        ?bool $stateless = null,
        ?string $env = null,
    ) {
        if (\array_key_exists(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, $defaults)) {
            throw new \InvalidArgumentException(\sprintf(
                'The defaults of "%s" must not define "%s"; use Symfony Route for custom or multiple scopes.',
                static::class,
                PlatformRequest::ATTRIBUTE_ROUTE_SCOPE,
            ));
        }

        $defaults[PlatformRequest::ATTRIBUTE_ROUTE_SCOPE] = [static::scope()];

        parent::__construct(
            path: $path,
            name: $name,
            requirements: $requirements,
            options: $options,
            defaults: $defaults,
            host: $host,
            methods: $methods,
            schemes: $schemes,
            condition: $condition,
            priority: $priority,
            locale: $locale,
            format: $format,
            utf8: $utf8,
            stateless: $stateless,
            env: $env,
        );
    }

    abstract protected static function scope(): string;
}
