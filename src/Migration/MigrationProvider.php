<?php declare(strict_types=1);

namespace Frosh\Jetpack\Migration;

use Shopware\Core\Framework\Bundle;

/**
 * @internal
 */
interface MigrationProvider
{
    /**
     * @return list<Migration>
     */
    public function forBundle(Bundle $bundle): array;
}
