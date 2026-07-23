<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final class BundleConfigurationLocator
{
    /**
     * @var array<string, BundleReference>|null
     */
    private ?array $references = null;

    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    /**
     * @return list<BundleReference>
     */
    public function all(): array
    {
        return array_values($this->references());
    }

    public function find(string $bundle): BundleReference
    {
        foreach ($this->references() as $reference) {
            if ($reference->class === $bundle || $reference->name === $bundle) {
                return $reference;
            }
        }

        throw ConfigurationException::bundleNotConfigured($bundle);
    }

    /**
     * @return array<string, BundleReference>
     */
    private function references(): array
    {
        if ($this->references !== null) {
            return $this->references;
        }

        $references = [];

        foreach ($this->kernel->getBundles() as $bundle) {
            $reference = new BundleReference($bundle::class, $bundle->getName(), $bundle->getPath());
            if (!is_file($reference->configurationPath())) {
                continue;
            }

            $references[$reference->name] = $reference;
        }

        return $this->references = $references;
    }
}
