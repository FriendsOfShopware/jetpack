<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Field;

use Doctrine\DBAL\Types\Types;
use Frosh\Jetpack\Entity\FieldSerializer\BackedEnumFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;

/**
 * Jetpack-owned enum field, available on every supported Shopware 6.6 and 6.7 release.
 */
final class BackedEnumField extends Field implements StorageAware
{
    /**
     * @var Types::STRING|Types::INTEGER
     */
    private readonly string $type;

    /**
     * @param class-string<\BackedEnum> $enumClass
     */
    public function __construct(
        private readonly string $storageName,
        string $propertyName,
        private readonly string $enumClass,
    ) {
        parent::__construct($propertyName);

        $backingType = (new \ReflectionEnum($enumClass))->getBackingType()?->getName();
        $this->type = match ($backingType) {
            'int' => Types::INTEGER,
            'string' => Types::STRING,
            default => throw new \InvalidArgumentException(\sprintf('Enum "%s" must be backed by string or int.', $enumClass)),
        };
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * @return class-string<\BackedEnum>
     */
    public function getEnumClass(): string
    {
        return $this->enumClass;
    }

    /**
     * @return Types::STRING|Types::INTEGER
     */
    public function getType(): string
    {
        return $this->type;
    }

    protected function getSerializerClass(): string
    {
        return BackedEnumFieldSerializer::class;
    }
}
