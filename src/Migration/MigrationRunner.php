<?php declare(strict_types=1);

namespace Frosh\Jetpack\Migration;

use Shopware\Core\Framework\Bundle;

interface MigrationRunner
{
    /**
     * @return list<class-string<Migration>>
     */
    public function up(Bundle $bundle): array;

    /**
     * @return list<class-string<Migration>>
     */
    public function down(Bundle $bundle): array;
}
