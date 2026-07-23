<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Schema;

final readonly class IndexSchema
{
    /**
     * @param non-empty-list<string> $columns
     */
    public function __construct(
        public string $name,
        public array $columns,
        public bool $unique = false,
    ) {
    }

    /**
     * @return array{name: string, columns: non-empty-list<string>, unique: bool}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'columns' => $this->columns, 'unique' => $this->unique];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!\is_string($data['name'] ?? null) || !\is_array($data['columns'] ?? null) || !\is_bool($data['unique'] ?? null)) {
            throw new \InvalidArgumentException('Invalid index snapshot.');
        }

        $columns = [];
        foreach ($data['columns'] as $column) {
            if (!\is_string($column)) {
                throw new \InvalidArgumentException('Index columns must be strings.');
            }
            $columns[] = $column;
        }
        if ($columns === []) {
            throw new \InvalidArgumentException('An index needs at least one column.');
        }

        return new self($data['name'], $columns, $data['unique']);
    }
}
