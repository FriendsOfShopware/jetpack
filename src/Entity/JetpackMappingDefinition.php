<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity;

use Frosh\Jetpack\Attribute\FieldType;
use Frosh\Jetpack\Entity\Schema\EntitySchema;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

abstract class JetpackMappingDefinition extends MappingEntityDefinition implements JetpackDefinition
{
    /**
     * @var array<class-string, EntitySchema>
     */
    private static array $schemas = [];

    /**
     * @return non-empty-string
     */
    final public function getEntityName(): string
    {
        return $this->getJetpackSchema()->entityName;
    }

    final public function getJetpackSchema(): EntitySchema
    {
        return self::$schemas[static::class] ??= (new EntitySchemaCompiler())->compile(static::class);
    }

    final public function isVersionAware(): bool
    {
        foreach ($this->getJetpackSchema()->fields as $field) {
            if ($field->type === FieldType::ReferenceVersion) {
                return true;
            }
        }

        return false;
    }

    final protected function defineFields(): FieldCollection
    {
        return (new DalFieldCompiler())->compile($this->getJetpackSchema());
    }
}
