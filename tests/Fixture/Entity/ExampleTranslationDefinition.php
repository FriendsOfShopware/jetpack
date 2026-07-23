<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture\Entity;

use Frosh\Jetpack\Attribute\TranslationEntity;
use Frosh\Jetpack\Entity\JetpackTranslationDefinition;

#[TranslationEntity(parent: ExampleDefinition::class)]
final class ExampleTranslationDefinition extends JetpackTranslationDefinition
{
}
