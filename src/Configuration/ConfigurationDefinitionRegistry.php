<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

/**
 * @internal
 */
final class ConfigurationDefinitionRegistry
{
    /**
     * @var array<string, ConfigurationDefinition>
     */
    private array $definitions = [];

    public function __construct(
        private readonly BundleConfigurationLocator $locator,
        private readonly YamlConfigurationLoader $loader,
    ) {
    }

    public function get(string $bundle): ConfigurationDefinition
    {
        $reference = $this->locator->find($bundle);

        return $this->definitions[$reference->name] ??= $this->loader->load($reference);
    }

    /**
     * @return list<ConfigurationDefinition>
     */
    public function all(): array
    {
        return array_map($this->get(...), array_map(
            static fn (BundleReference $bundle): string => $bundle->name,
            $this->locator->all(),
        ));
    }
}
