<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Migration;

final class UnsafeSchemaChangeException extends \RuntimeException
{
    public static function requiredColumnWithoutDefault(string $table, string $column): self
    {
        return new self(\sprintf(
            'Cannot add required column "%s.%s" to an existing table without a database default. Add it nullable first, configure hasDefault/default, or write a custom expand-and-contract migration.',
            $table,
            $column,
        ));
    }
}
