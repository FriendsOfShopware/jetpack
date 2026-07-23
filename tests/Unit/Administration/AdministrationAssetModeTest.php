<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Administration;

use Frosh\Jetpack\Administration\AdministrationAssetMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

final class AdministrationAssetModeTest extends TestCase
{
    private string $entrypointsPath;

    protected function setUp(): void
    {
        $entrypointsPath = tempnam(sys_get_temp_dir(), 'frosh-jetpack-entrypoints-');

        if ($entrypointsPath === false) {
            static::fail('Could not create an entrypoints fixture.');
        }

        $this->entrypointsPath = $entrypointsPath;
    }

    protected function tearDown(): void
    {
        if (isset($this->entrypointsPath) && is_file($this->entrypointsPath)) {
            unlink($this->entrypointsPath);
        }
    }

    #[DataProvider('viteVersionProvider')]
    public function testShopware67UsesVite(string $version): void
    {
        static::assertTrue((new AdministrationAssetMode($version))->usesVite());
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function viteVersionProvider(): \Generator
    {
        yield 'first 6.7 release' => ['6.7.0.0'];
        yield 'development version' => ['6.7.9999999-dev'];
    }

    public function testTwigFunctionIsRegistered(): void
    {
        $functions = (new AdministrationAssetMode('6.7.0.0'))->getFunctions();

        static::assertCount(2, $functions);
        static::assertSame('frosh_jetpack_uses_vite_administration', $functions[0]->getName());
        static::assertSame('frosh_jetpack_administration_assets', $functions[1]->getName());
    }

    public function testShopware66UsesWebpackWhenTheViteFeatureIsDisabled(): void
    {
        static::assertFalse((new AdministrationAssetMode('6.6.9.0'))->usesVite());
    }

    public function testAssetsAreReadFromTheViteEntrypointsManifest(): void
    {
        file_put_contents($this->entrypointsPath, json_encode([
            'entryPoints' => [
                'frosh-jetpack' => [
                    'js' => ['/bundles/froshjetpack/administration/assets/frosh-jetpack.js'],
                    'css' => [
                        '/bundles/froshjetpack/administration/assets/frosh-jetpack.css',
                    ],
                ],
            ],
        ], \JSON_THROW_ON_ERROR));

        $assetMode = new AdministrationAssetMode('6.7.0.0', $this->entrypointsPath);
        $expected = [
            'script' => 'bundles/froshjetpack/administration/assets/frosh-jetpack.js',
            'styles' => [
                'bundles/froshjetpack/administration/assets/frosh-jetpack.css',
            ],
        ];

        static::assertSame($expected, $assetMode->assets());
        file_put_contents($this->entrypointsPath, '{}');
        static::assertSame($expected, $assetMode->assets());
    }

    public function testShippedAdministrationEntrypointIsReadable(): void
    {
        $assets = (new AdministrationAssetMode('6.7.0.0'))->assets();

        static::assertStringStartsWith(
            'bundles/froshjetpack/administration/assets/frosh-jetpack-',
            $assets['script'],
        );
    }

    public function testMissingEntrypointIsRejected(): void
    {
        file_put_contents($this->entrypointsPath, '{}');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Administration entrypoint is missing');

        (new AdministrationAssetMode('6.7.0.0', $this->entrypointsPath))->assets();
    }

    public function testClassicShopware66TemplatePatchesApplicationBeforeTheCoreScriptStarts(): void
    {
        $html = $this->renderTemplate('6.6.0.0', <<<'TWIG'
            <!DOCTYPE html>
            <html><body>
            {% block administration_templates %}{% endblock %}
            <script src="app.js"></script>
            <script>Shopware.Application.start({});</script>
            </body></html>
            TWIG);

        static::assertStringContainsString('Object.defineProperty(globalThis, \'Shopware\'', $html);
        static::assertStringContainsString('type="module"', $html);
        static::assertStringContainsString('/bundles/froshjetpack/administration/assets/frosh-jetpack.js', $html);
    }

    public function testViteTemplateWaitsForJetpackBeforeStartingTheApplication(): void
    {
        $html = $this->renderTemplate('6.7.0.0', <<<'TWIG'
            <!DOCTYPE html>
            <html><body>
            {% block administration_content %}
                {% block administration_templates %}{% endblock %}
                <script type="module" src="administration.js"></script>
                {% block administration_login_scripts %}{% endblock %}
                <script>window.startApplication = () => Shopware.Application.start({});</script>
            {% endblock %}
            </body></html>
            TWIG);

        static::assertStringNotContainsString('Object.defineProperty(globalThis, \'Shopware\'', $html);
        static::assertStringContainsString('type="module"', $html);
        static::assertStringContainsString('window.startApplication = (...args) => entry.ready.then', $html);
    }

    private function renderTemplate(string $shopwareVersion, string $baseTemplate): string
    {
        file_put_contents($this->entrypointsPath, json_encode([
            'entryPoints' => [
                'frosh-jetpack' => [
                    'js' => ['/bundles/froshjetpack/administration/assets/frosh-jetpack.js'],
                    'css' => [],
                ],
            ],
        ], \JSON_THROW_ON_ERROR));

        $views = \dirname(__DIR__, 3) . '/src/Resources/views/administration';
        $index = file_get_contents($views . '/index.html.twig');
        $entry = file_get_contents($views . '/entry.html.twig');

        static::assertIsString($index);
        static::assertIsString($entry);

        $index = str_replace(
            '{% sw_extends \'@Administration/administration/index.html.twig\' %}',
            '{% extends \'base.html.twig\' %}',
            $index,
        );

        $twig = new Environment(new ArrayLoader([
            'index.html.twig' => $index,
            'base.html.twig' => $baseTemplate,
            '@FroshJetpack/administration/entry.html.twig' => $entry,
        ]));
        $twig->addExtension(new AdministrationAssetMode($shopwareVersion, $this->entrypointsPath));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => '/' . $path));

        return $twig->render('index.html.twig', ['cspNonce' => 'test-nonce']);
    }
}
