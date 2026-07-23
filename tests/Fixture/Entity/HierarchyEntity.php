<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture\Entity;

use Frosh\Jetpack\Attribute\ApiScope;
use Frosh\Jetpack\Attribute\ForeignKey;
use Frosh\Jetpack\Attribute\Id;
use Frosh\Jetpack\Attribute\ManyToOne;
use Frosh\Jetpack\Attribute\OnDelete;
use Frosh\Jetpack\Attribute\OneToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

final class HierarchyEntity extends Entity
{
    #[Id]
    public string $id;

    #[ForeignKey(HierarchyDefinition::class, onDelete: OnDelete::Cascade, api: ApiScope::All)]
    public ?string $parentId = null;

    #[ManyToOne(HierarchyDefinition::class, localField: 'parentId', api: ApiScope::All)]
    public ?HierarchyEntity $parent = null;

    #[OneToMany(HierarchyDefinition::class, referenceField: 'parentId', api: ApiScope::All)]
    public ?HierarchyCollection $children = null;
}
