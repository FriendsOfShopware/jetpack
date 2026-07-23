<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

/**
 * @internal
 */
final readonly class BundleReference
{
    public const RELATIVE_PATH = '/Resources/config/jetpack.yaml';

    /**
     * @param class-string $class
     */
    public function __construct(
        public string $class,
        public string $name,
        public string $path,
    ) {
    }

    public function configurationPath(): string
    {
        return $this->path . self::RELATIVE_PATH;
    }
}
