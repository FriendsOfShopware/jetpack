<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Migration;

final readonly class RenderedMigration
{
    public function __construct(
        public string $className,
        public string $content,
    ) {
    }
}
