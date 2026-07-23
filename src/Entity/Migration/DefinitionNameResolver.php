<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Migration;

use Frosh\Jetpack\Entity\JetpackDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * @internal
 */
final class DefinitionNameResolver
{
    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    public function entityName(string $definitionClass): string
    {
        if (\is_subclass_of($definitionClass, JetpackDefinition::class)) {
            $definition = new $definitionClass();

            return $definition->getJetpackSchema()->entityName;
        }
        $constant = $definitionClass . '::ENTITY_NAME';
        if (\defined($constant)) {
            $name = \constant($constant);
            if (\is_string($name)) {
                return $name;
            }
        }
        $definition = new $definitionClass();

        return $definition->getEntityName();
    }
}
