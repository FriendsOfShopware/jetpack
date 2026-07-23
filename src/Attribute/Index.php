<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class Index
{
    /**
     * @param list<string> $fields Property names, not storage column names; the compiler rejects an empty list
     */
    public function __construct(
        public array $fields,
        public ?string $name = null,
        public bool $unique = false,
    ) {
    }
}
