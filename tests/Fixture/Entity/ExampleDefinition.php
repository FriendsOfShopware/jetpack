<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture\Entity;

use Frosh\Jetpack\Attribute\Entity;
use Frosh\Jetpack\Attribute\Index;
use Frosh\Jetpack\Entity\JetpackEntityDefinition;

#[Entity(
    name: 'frosh_jetpack_example',
    entity: ExampleEntity::class,
    collection: ExampleCollection::class,
    translationDefinition: ExampleTranslationDefinition::class,
    versioned: true,
)]
#[Index(['productId'], unique: true)]
final class ExampleDefinition extends JetpackEntityDefinition
{
}
