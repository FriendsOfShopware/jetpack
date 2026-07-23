<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

use Shopware\Core\Framework\Context;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
final class DefaultConfigurationService implements ConfigurationService, ResetInterface
{
    /**
     * @var array<string, list<StoredConfigurationValue>>
     */
    private array $storedValues = [];

    public function __construct(
        private readonly ConfigurationDefinitionRegistry $definitions,
        private readonly ConfigurationStore $store,
        private readonly ConfigurationValueResolver $resolver,
        private readonly DtoHydrator $dtoHydrator,
    ) {
    }

    public function get(string $bundle, string $key, Context $context, ?string $salesChannelId = null): mixed
    {
        return $this->resolved($bundle, $key, $context, $salesChannelId)->value;
    }

    public function getBool(string $bundle, string $key, Context $context, ?string $salesChannelId = null): bool
    {
        $value = $this->get($bundle, $key, $context, $salesChannelId);
        if (!\is_bool($value)) {
            throw ConfigurationException::invalidValue($key, 'bool', $value);
        }

        return $value;
    }

    public function getInt(string $bundle, string $key, Context $context, ?string $salesChannelId = null): int
    {
        $value = $this->get($bundle, $key, $context, $salesChannelId);
        if (!\is_int($value)) {
            throw ConfigurationException::invalidValue($key, 'int', $value);
        }

        return $value;
    }

    public function getFloat(string $bundle, string $key, Context $context, ?string $salesChannelId = null): float
    {
        $value = $this->get($bundle, $key, $context, $salesChannelId);
        if (!\is_float($value) && !\is_int($value)) {
            throw ConfigurationException::invalidValue($key, 'float', $value);
        }

        return (float) $value;
    }

    public function getString(string $bundle, string $key, Context $context, ?string $salesChannelId = null): string
    {
        $value = $this->get($bundle, $key, $context, $salesChannelId);
        if (!\is_string($value)) {
            throw ConfigurationException::invalidValue($key, 'string', $value);
        }

        return $value;
    }

    public function getList(string $bundle, string $key, Context $context, ?string $salesChannelId = null): array
    {
        $value = $this->get($bundle, $key, $context, $salesChannelId);
        if (!\is_array($value) || !array_is_list($value)) {
            throw ConfigurationException::invalidValue($key, 'list', $value);
        }

        return $value;
    }

    public function load(string $configClass, Context $context, ?string $salesChannelId = null): object
    {
        $bundle = $this->dtoHydrator->bundle($configClass);

        return $this->dtoHydrator->hydrate(
            $configClass,
            fn (string $key): mixed => $this->get($bundle, $key, $context, $salesChannelId),
        );
    }

    public function resolved(string $bundle, string $key, Context $context, ?string $salesChannelId = null): ResolvedValue
    {
        $definition = $this->definitions->get($bundle);

        return $this->resolver->resolve(
            $definition->field($key),
            $this->storedValues[$definition->bundle->name] ??= $this->store->load($definition->bundle->name),
            $context->getLanguageIdChain(),
            $salesChannelId,
        );
    }

    public function reset(): void
    {
        $this->storedValues = [];
    }
}
