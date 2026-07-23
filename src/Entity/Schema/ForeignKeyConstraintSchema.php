<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Schema;

use Frosh\Jetpack\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

final readonly class ForeignKeyConstraintSchema
{
    /**
     * @param non-empty-list<string> $localColumns
     * @param class-string<EntityDefinition> $targetDefinition
     * @param non-empty-list<string> $referenceFields Target DAL property names
     */
    public function __construct(
        public string $name,
        public array $localColumns,
        public string $targetDefinition,
        public array $referenceFields,
        public OnDelete $onDelete,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'localColumns' => $this->localColumns,
            'targetDefinition' => $this->targetDefinition,
            'referenceFields' => $this->referenceFields,
            'onDelete' => $this->onDelete->value,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!\is_string($data['name'] ?? null)
            || !\is_array($data['localColumns'] ?? null)
            || !\is_string($data['targetDefinition'] ?? null)
            || !\is_array($data['referenceFields'] ?? null)
            || !\is_string($data['onDelete'] ?? null)
        ) {
            throw new \InvalidArgumentException('Invalid foreign-key constraint snapshot.');
        }
        $localColumns = self::stringList($data['localColumns']);
        $referenceFields = self::stringList($data['referenceFields']);

        /** @var class-string<EntityDefinition> $targetDefinition Historical snapshots may reference a removed definition. */
        $targetDefinition = $data['targetDefinition'];

        return new self(
            $data['name'],
            $localColumns,
            $targetDefinition,
            $referenceFields,
            OnDelete::from($data['onDelete']),
        );
    }

    /**
     * @param array<mixed> $values
     *
     * @return non-empty-list<string>
     */
    private static function stringList(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            if (!\is_string($value)) {
                throw new \InvalidArgumentException('Foreign-key constraint columns must be strings.');
            }
            $result[] = $value;
        }
        if ($result === []) {
            throw new \InvalidArgumentException('Foreign-key constraint needs at least one column.');
        }

        return $result;
    }
}
