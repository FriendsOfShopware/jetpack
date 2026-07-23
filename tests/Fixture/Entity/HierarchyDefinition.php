<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture\Entity;

use Frosh\Jetpack\Attribute\Entity;
use Frosh\Jetpack\Entity\JetpackEntityDefinition;

#[Entity(
    name: 'frosh_jetpack_hierarchy',
    entity: HierarchyEntity::class,
    collection: HierarchyCollection::class,
    inheritanceAware: true,
)]
final class HierarchyDefinition extends JetpackEntityDefinition
{
}
