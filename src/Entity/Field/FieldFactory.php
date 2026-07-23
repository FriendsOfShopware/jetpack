<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Field;

use Frosh\Jetpack\Entity\Schema\FieldSchema;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

/**
 * Extension point for specialized DAL fields that need deterministic migration storage.
 */
interface FieldFactory
{
    public function create(FieldSchema $schema): Field;

    /**
     * Returns a MySQL/MariaDB column type fragment, for example VARCHAR(64) or DECIMAL(10, 2).
     */
    public function sqlType(FieldSchema $schema): string;
}
