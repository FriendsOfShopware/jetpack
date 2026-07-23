<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture;

use Frosh\Jetpack\CustomField\CustomFieldSetStore;
use Shopware\Core\Framework\Context;

final class InMemoryCustomFieldSetStore implements CustomFieldSetStore
{
    /**
     * @var array<string, array<string, string>>
     */
    public array $ownership = [];

    /**
     * @var array<string, array{relations: array<string, string>, fields: array<string, array{id: string, type: string}>}>
     */
    public array $childrenBySet = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $upserts = [];

    /**
     * @var list<string>
     */
    public array $deletedSets = [];

    /**
     * @var list<string>
     */
    public array $deletedRelations = [];

    /**
     * @var list<string>
     */
    public array $deletedFields = [];

    public bool $includeInSearch = false;

    /**
     * @var array<string, true>
     */
    public array $existingSetIds = [];

    public function owned(string $bundleName): array
    {
        return $this->ownership[$bundleName] ?? [];
    }

    public function children(string $setId): array
    {
        return $this->childrenBySet[$setId] ?? ['relations' => [], 'fields' => []];
    }

    public function setExists(string $setId): bool
    {
        return isset($this->existingSetIds[$setId]);
    }

    public function upsert(array $sets, Context $context): void
    {
        $this->upserts = array_merge($this->upserts, $sets);
    }

    public function deleteSets(array $ids, Context $context): void
    {
        $this->deletedSets = array_merge($this->deletedSets, $ids);
    }

    public function deleteRelations(array $ids, Context $context): void
    {
        $this->deletedRelations = array_merge($this->deletedRelations, $ids);
    }

    public function deleteFields(array $ids, Context $context): void
    {
        $this->deletedFields = array_merge($this->deletedFields, $ids);
    }

    public function replaceOwnership(string $bundleName, array $sets): void
    {
        $this->ownership[$bundleName] = $sets;
    }

    public function supportsIncludeInSearch(): bool
    {
        return $this->includeInSearch;
    }
}
