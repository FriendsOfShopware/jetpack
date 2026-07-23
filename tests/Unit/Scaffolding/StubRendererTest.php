<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Scaffolding;

use Frosh\Jetpack\Scaffolding\ScaffoldNaming;
use Frosh\Jetpack\Scaffolding\StubRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScaffoldNaming::class)]
#[CoversClass(StubRenderer::class)]
final class StubRendererTest extends TestCase
{
    public function testRendersEveryDeclaredPlaceholder(): void
    {
        $rendered = (new StubRenderer())->render(
            'namespace %jetpack.namespace%; final class %jetpack.class_name% {}',
            ['namespace' => 'Acme\\Review', 'class_name' => 'Review'],
        );

        static::assertSame('namespace Acme\\Review; final class Review {}', $rendered);
    }

    public function testRejectsMissingPlaceholderValues(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Missing scaffold value for placeholder "class_name".'));

        (new StubRenderer())->render('final class %jetpack.class_name% {}', []);
    }

    public function testRejectsUnusedValues(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Scaffold value "unused" is not used by the template.'));

        (new StubRenderer())->render('final class Review {}', ['unused' => 'value']);
    }

    #[DataProvider('names')]
    public function testNormalizesTechnicalNames(string $className, string $snakeCase, string $kebabCase): void
    {
        ScaffoldNaming::assertPascalCase($className, 'Class');

        static::assertSame($snakeCase, ScaffoldNaming::snakeCase($className));
        static::assertSame($kebabCase, ScaffoldNaming::kebabCase($className));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function names(): iterable
    {
        yield 'ordinary PascalCase' => ['RebuildIndex', 'rebuild_index', 'rebuild-index'];
        yield 'acronym boundary' => ['ImportURLMappings', 'import_url_mappings', 'import-url-mappings'];
    }
}
