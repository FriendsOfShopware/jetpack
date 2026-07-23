<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity;

use Frosh\Jetpack\Entity\Schema\EntitySchema;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Public base class for concrete, zero-constructor Jetpack entity definitions.
 */
abstract class JetpackEntityDefinition extends EntityDefinition implements JetpackDefinition
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

    /**
     * @return class-string<Entity>
     */
    final public function getEntityClass(): string
    {
        return $this->getJetpackSchema()->entityClass;
    }

    /**
     * @return class-string
     */
    final public function getCollectionClass(): string
    {
        return $this->getJetpackSchema()->collectionClass;
    }

    final public function isInheritanceAware(): bool
    {
        return $this->getJetpackSchema()->inheritanceAware;
    }

    final public function getJetpackSchema(): EntitySchema
    {
        return self::$schemas[static::class] ??= (new EntitySchemaCompiler())->compile(static::class);
    }

    final protected function getParentDefinitionClass(): ?string
    {
        return $this->getJetpackSchema()->parentDefinition;
    }

    final protected function defineFields(): FieldCollection
    {
        return (new DalFieldCompiler())->compile($this->getJetpackSchema());
    }
}
