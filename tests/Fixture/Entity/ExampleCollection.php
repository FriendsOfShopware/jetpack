<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ExampleEntity>
 */
final class ExampleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ExampleEntity::class;
    }
}
