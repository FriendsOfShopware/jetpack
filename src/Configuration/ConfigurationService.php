<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

use Shopware\Core\Framework\Context;

interface ConfigurationService
{
    /**
     * @param class-string $bundle
     */
    public function get(string $bundle, string $key, Context $context, ?string $salesChannelId = null): mixed;

    /**
     * @param class-string $bundle
     */
    public function getBool(string $bundle, string $key, Context $context, ?string $salesChannelId = null): bool;

    /**
     * @param class-string $bundle
     */
    public function getInt(string $bundle, string $key, Context $context, ?string $salesChannelId = null): int;

    /**
     * @param class-string $bundle
     */
    public function getFloat(string $bundle, string $key, Context $context, ?string $salesChannelId = null): float;

    /**
     * @param class-string $bundle
     */
    public function getString(string $bundle, string $key, Context $context, ?string $salesChannelId = null): string;

    /**
     * @param class-string $bundle
     *
     * @return list<mixed>
     */
    public function getList(string $bundle, string $key, Context $context, ?string $salesChannelId = null): array;

    /**
     * @template TConfig of object
     *
     * @param class-string<TConfig> $configClass
     *
     * @return TConfig
     */
    public function load(string $configClass, Context $context, ?string $salesChannelId = null): object;

    /**
     * @param class-string $bundle
     */
    public function resolved(string $bundle, string $key, Context $context, ?string $salesChannelId = null): ResolvedValue;
}
