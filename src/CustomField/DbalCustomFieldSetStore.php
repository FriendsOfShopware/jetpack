<?php declare(strict_types=1);

namespace Frosh\Jetpack\CustomField;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationCollection;
use Shopware\Core\System\CustomField\CustomFieldCollection;

/**
 * @internal
 */
final class DbalCustomFieldSetStore implements CustomFieldSetStore
{
    /**
     * @param EntityRepository<CustomFieldSetCollection> $setRepository
     * @param EntityRepository<CustomFieldSetRelationCollection> $relationRepository
     * @param EntityRepository<CustomFieldCollection> $fieldRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $setRepository,
        private readonly EntityRepository $relationRepository,
        private readonly EntityRepository $fieldRepository,
    ) {
    }

    public function owned(string $bundleName): array
    {
        /** @var array<string, string> $sets */
        $sets = $this->connection->fetchAllKeyValue(
            <<<'SQL'
                SELECT `set_name`, LOWER(HEX(`set_id`))
                FROM `frosh_jetpack_custom_field_set`
                WHERE `bundle_name` = :bundle
                SQL,
            ['bundle' => $bundleName],
        );

        return $sets;
    }

    public function children(string $setId): array
    {
        /** @var array<string, string> $relations */
        $relations = $this->connection->fetchAllKeyValue(
            'SELECT `entity_name`, LOWER(HEX(`id`)) FROM `custom_field_set_relation` WHERE `set_id` = :setId',
            ['setId' => Uuid::fromHexToBytes($setId)],
        );
        $fieldRows = $this->connection->fetchAllAssociative(
            'SELECT `name`, LOWER(HEX(`id`)) AS `id`, `type` FROM `custom_field` WHERE `set_id` = :setId',
            ['setId' => Uuid::fromHexToBytes($setId)],
        );
        $fields = [];
        foreach ($fieldRows as $row) {
            $name = $row['name'] ?? null;
            $id = $row['id'] ?? null;
            $type = $row['type'] ?? null;
            if (!\is_string($name) || !\is_string($id) || !\is_string($type)) {
                throw new \RuntimeException('The custom field table contains an invalid row.');
            }

            $fields[$name] = ['id' => $id, 'type' => $type];
        }

        return ['relations' => $relations, 'fields' => $fields];
    }

    public function setExists(string $setId): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM `custom_field_set` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($setId)],
        ) !== false;
    }

    public function upsert(array $sets, Context $context): void
    {
        if ($sets !== []) {
            $this->setRepository->upsert($sets, $context);
        }
    }

    public function deleteSets(array $ids, Context $context): void
    {
        $this->delete($this->setRepository, $ids, $context);
    }

    public function deleteRelations(array $ids, Context $context): void
    {
        $this->delete($this->relationRepository, $ids, $context);
    }

    public function deleteFields(array $ids, Context $context): void
    {
        $this->delete($this->fieldRepository, $ids, $context);
    }

    public function replaceOwnership(string $bundleName, array $sets): void
    {
        $this->connection->delete('frosh_jetpack_custom_field_set', ['bundle_name' => $bundleName]);
        foreach ($sets as $name => $id) {
            $this->connection->insert('frosh_jetpack_custom_field_set', [
                'bundle_name' => $bundleName,
                'set_name' => $name,
                'set_id' => Uuid::fromHexToBytes($id),
            ]);
        }
    }

    public function supportsIncludeInSearch(): bool
    {
        return $this->fieldRepository->getDefinition()->getField('includeInSearch') !== null;
    }

    /**
     * @template TCollection of \Shopware\Core\Framework\DataAbstractionLayer\EntityCollection
     *
     * @param EntityRepository<TCollection> $repository
     * @param list<string> $ids
     */
    private function delete(EntityRepository $repository, array $ids, Context $context): void
    {
        if ($ids === []) {
            return;
        }

        $repository->delete(array_map(static fn (string $id): array => ['id' => $id], $ids), $context);
    }
}
