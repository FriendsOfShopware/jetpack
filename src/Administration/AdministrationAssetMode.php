<?php declare(strict_types=1);

namespace Frosh\Jetpack\Administration;

use Shopware\Core\Framework\Feature;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @internal
 */
final class AdministrationAssetMode extends AbstractExtension
{
    /**
     * @var array{script: string, styles: list<string>}|null
     */
    private ?array $assets = null;

    public function __construct(
        private readonly string $shopwareVersion,
        private readonly ?string $entrypointsPath = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('frosh_jetpack_uses_vite_administration', $this->usesVite(...)),
            new TwigFunction('frosh_jetpack_administration_assets', $this->assets(...)),
        ];
    }

    public function usesVite(): bool
    {
        if (version_compare($this->shopwareVersion, '6.7.0.0', '>=')) {
            return true;
        }

        return Feature::isActive('ADMIN_VITE');
    }

    /**
     * @return array{script: string, styles: list<string>}
     */
    public function assets(): array
    {
        if ($this->assets !== null) {
            return $this->assets;
        }

        $path = $this->entrypointsPath ?? \dirname(__DIR__) . '/Resources/public/administration/.vite/entrypoints.json';
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException(\sprintf('Cannot read the Frosh Jetpack Administration entrypoints at "%s".', $path));
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            throw new \RuntimeException(\sprintf('The Frosh Jetpack Administration entrypoints are invalid in "%s".', $path));
        }

        $entrypoint = $decoded['entryPoints']['frosh-jetpack'] ?? null;

        if (!\is_array($entrypoint) || !isset($entrypoint['js'][0]) || !\is_string($entrypoint['js'][0])) {
            throw new \RuntimeException(\sprintf('The Frosh Jetpack Administration entrypoint is missing in "%s".', $path));
        }

        $styles = $entrypoint['css'] ?? [];

        if (!\is_array($styles) || !array_is_list($styles)) {
            throw new \RuntimeException(\sprintf('The Frosh Jetpack Administration styles are invalid in "%s".', $path));
        }

        foreach ($styles as $style) {
            if (!\is_string($style)) {
                throw new \RuntimeException(\sprintf('The Frosh Jetpack Administration styles are invalid in "%s".', $path));
            }
        }

        return $this->assets = [
            'script' => ltrim($entrypoint['js'][0], '/'),
            'styles' => array_map(static fn (string $style): string => ltrim($style, '/'), $styles),
        ];
    }
}
