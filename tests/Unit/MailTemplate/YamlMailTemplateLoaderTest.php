<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\MailTemplate;

use Frosh\Jetpack\MailTemplate\MailTemplateException;
use Frosh\Jetpack\MailTemplate\YamlMailTemplateLoader;
use Frosh\Jetpack\Tests\Fixture\InvalidMailTemplateBundle;
use Frosh\Jetpack\Tests\Fixture\MailTemplateTestBundle;
use Frosh\Jetpack\Tests\Fixture\MigrationTestBundle;
use Frosh\Jetpack\Tests\Fixture\MissingDefaultMailTemplateBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(YamlMailTemplateLoader::class)]
#[CoversClass(MailTemplateException::class)]
final class YamlMailTemplateLoaderTest extends TestCase
{
    public function testLoadsMetadataAndConventionalTwigFiles(): void
    {
        $manifest = $this->loader(['order', 'sales_channel'])->load(new MailTemplateTestBundle());

        static::assertSame('en-GB', $manifest->defaultLocale);
        static::assertCount(1, $manifest->templates);
        $template = $manifest->templates[0];
        static::assertSame('acme_order_confirmation', $template['technicalName']);
        static::assertSame([
            'order' => 'order',
            'salesChannel' => 'sales_channel',
            'editOrderUrl' => null,
        ], $template['availableEntities']);
        static::assertSame('preserve-user-changes', $template['updatePolicy']);
        static::assertCount(2, $template['translations']);
        static::assertStringContainsString('{{ order.orderNumber }}', $template['translations']['en-GB']['contentHtml']);
        static::assertStringContainsString('Ihre Bestellung', $template['translations']['de-DE']['contentPlain']);
    }

    public function testReturnsEmptyManifestWhenYamlDoesNotExist(): void
    {
        $manifest = $this->loader([])->load(new MigrationTestBundle());

        static::assertSame([], $manifest->templates);
        static::assertStringEndsWith('/Resources/config/mail-templates.yaml', $manifest->path);
    }

    public function testRejectsUnknownAvailableEntity(): void
    {
        $this->expectException(MailTemplateException::class);
        $this->expectExceptionMessage('unknown DAL entity "unknown_entity"');

        $this->loader([])->load(new InvalidMailTemplateBundle());
    }

    public function testRejectsInvalidTwigSyntax(): void
    {
        $this->expectException(MailTemplateException::class);
        $this->expectExceptionMessage('Unexpected end of template');

        $this->loader(['unknown_entity'])->load(new InvalidMailTemplateBundle());
    }

    public function testRequiresDefaultLocaleForEveryTemplate(): void
    {
        $this->expectExceptionObject(MailTemplateException::invalidDefinition(
            (new MissingDefaultMailTemplateBundle())->getPath() . YamlMailTemplateLoader::RELATIVE_PATH,
            'Template "acme_missing_default" must declare the default locale "en-GB".',
        ));

        $this->loader([])->load(new MissingDefaultMailTemplateBundle());
    }

    /**
     * @param list<string> $entities
     */
    private function loader(array $entities): YamlMailTemplateLoader
    {
        $definitions = static::createStub(DefinitionInstanceRegistry::class);
        $definitions->method('has')->willReturnCallback(
            static fn (string $entityName): bool => \in_array($entityName, $entities, true),
        );

        return new YamlMailTemplateLoader(new Environment(new ArrayLoader()), $definitions);
    }
}
