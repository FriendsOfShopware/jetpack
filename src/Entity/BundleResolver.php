<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity;

use Shopware\Core\Framework\Bundle;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final class BundleResolver
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    public function resolve(string $name): Bundle
    {
        try {
            $bundle = $this->kernel->getBundle($name);
            if ($bundle instanceof Bundle) {
                return $bundle;
            }
        } catch (\InvalidArgumentException) {
        }

        foreach ($this->kernel->getBundles() as $bundle) {
            if (($bundle->getName() === $name || $bundle::class === $name) && $bundle instanceof Bundle) {
                return $bundle;
            }
        }

        throw new \InvalidArgumentException(\sprintf('Bundle "%s" is not an active Shopware bundle.', $name));
    }

    /**
     * @return list<Bundle>
     */
    public function all(): array
    {
        $bundles = [];
        foreach ($this->kernel->getBundles() as $bundle) {
            if ($bundle instanceof Bundle) {
                $bundles[] = $bundle;
            }
        }
        usort($bundles, static fn (Bundle $first, Bundle $second): int => $first->getName() <=> $second->getName());

        return $bundles;
    }
}
