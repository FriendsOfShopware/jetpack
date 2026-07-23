<?php declare(strict_types=1);

namespace Frosh\Jetpack\Migration;

/**
 * @internal
 */
interface MigrationLock
{
    /**
     * @template T
     *
     * @param \Closure(): T $callback
     *
     * @return T
     */
    public function synchronized(string $bundle, \Closure $callback): mixed;
}
