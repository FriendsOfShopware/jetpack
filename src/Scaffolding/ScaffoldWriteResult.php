<?php declare(strict_types=1);

namespace Frosh\Jetpack\Scaffolding;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class ScaffoldWriteResult
{
    /**
     * @param list<string> $created
     * @param list<string> $unchanged
     */
    public function __construct(
        public array $created,
        public array $unchanged,
    ) {
    }
}
