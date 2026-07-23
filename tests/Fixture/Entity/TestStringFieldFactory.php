<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture\Entity;

use Frosh\Jetpack\Entity\Field\FieldFactory;
use Frosh\Jetpack\Entity\Schema\FieldSchema;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;

final class TestStringFieldFactory implements FieldFactory
{
    public function create(FieldSchema $schema): Field
    {
        return new StringField($schema->storageName, $schema->propertyName, 64);
    }

    public function sqlType(FieldSchema $schema): string
    {
        return 'VARCHAR(64)';
    }
}
