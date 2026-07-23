<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity;

use Frosh\Jetpack\Entity\Schema\EntitySchema;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

abstract class JetpackTranslationDefinition extends EntityTranslationDefinition implements JetpackDefinition
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

    final public function getJetpackSchema(): EntitySchema
    {
        return self::$schemas[static::class] ??= (new EntitySchemaCompiler())->compile(static::class);
    }

    /**
     * @return class-string<EntityDefinition>
     */
    final protected function getParentDefinitionClass(): string
    {
        return $this->getJetpackSchema()->parentDefinition
            ?? throw new \LogicException('A Jetpack translation definition needs a parent definition.');
    }

    final protected function defineFields(): FieldCollection
    {
        return (new DalFieldCompiler())->compile($this->getJetpackSchema());
    }
}
