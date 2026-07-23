<?php declare(strict_types=1);

namespace Frosh\Jetpack\CustomField;

use Shopware\Core\Framework\Context;

/**
 * @internal
 */
interface CustomFieldSetStore
{
    /**
     * @return array<string, string> set name to hexadecimal id
     */
    public function owned(string $bundleName): array;

    /**
     * @return array{relations: array<string, string>, fields: array<string, array{id: string, type: string}>}
     */
    public function children(string $setId): array;

    public function setExists(string $setId): bool;

    /**
     * @param list<array<string, mixed>> $sets
     */
    public function upsert(array $sets, Context $context): void;

    /**
     * @param list<string> $ids
     */
    public function deleteSets(array $ids, Context $context): void;

    /**
     * @param list<string> $ids
     */
    public function deleteRelations(array $ids, Context $context): void;

    /**
     * @param list<string> $ids
     */
    public function deleteFields(array $ids, Context $context): void;

    /**
     * @param array<string, string> $sets set name to hexadecimal id
     */
    public function replaceOwnership(string $bundleName, array $sets): void;

    public function supportsIncludeInSearch(): bool;
}
