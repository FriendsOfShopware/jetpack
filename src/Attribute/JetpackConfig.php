<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class JetpackConfig
{
    /**
     * @param class-string $bundle
     */
    public function __construct(public string $bundle)
    {
    }
}
