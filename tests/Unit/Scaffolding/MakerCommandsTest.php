<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Scaffolding;

use Frosh\Jetpack\Attribute\AsScheduledTask;
use Frosh\Jetpack\Command\AbstractMakeCommand;
use Frosh\Jetpack\Command\MakeCmsElementCommand;
use Frosh\Jetpack\Command\MakeConsoleCommand;
use Frosh\Jetpack\Command\MakeEntityCommand;
use Frosh\Jetpack\Command\MakeMigrationCommand;
use Frosh\Jetpack\Command\MakeScheduledTaskCommand;
use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\Migration\Migration;
use Frosh\Jetpack\Scaffolding\Generator\CmsElementScaffolder;
use Frosh\Jetpack\Scaffolding\Generator\CommandScaffolder;
use Frosh\Jetpack\Scaffolding\Generator\EntityScaffolder;
use Frosh\Jetpack\Scaffolding\Generator\MigrationScaffolder;
use Frosh\Jetpack\Scaffolding\Generator\ScheduledTaskScaffolder;
use Frosh\Jetpack\Scaffolding\ScaffoldWriter;
use Frosh\Jetpack\Scaffolding\StubRenderer;
use Frosh\Jetpack\ScheduledTask\ScheduledTaskDeclarationValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Source;

#[CoversClass(AbstractMakeCommand::class)]
#[CoversClass(MakeCmsElementCommand::class)]
#[CoversClass(MakeConsoleCommand::class)]
#[CoversClass(MakeEntityCommand::class)]
#[CoversClass(MakeMigrationCommand::class)]
#[CoversClass(MakeScheduledTaskCommand::class)]
#[CoversClass(CmsElementScaffolder::class)]
#[CoversClass(CommandScaffolder::class)]
#[CoversClass(EntityScaffolder::class)]
#[CoversClass(MigrationScaffolder::class)]
#[CoversClass(ScheduledTaskScaffolder::class)]
final class MakerCommandsTest extends TestCase
{
    private Filesystem $filesystem;

    private string $temporaryDirectory;

    private ScaffoldTestBundle $bundle;

    private BundleResolver $bundleResolver;

    private ScaffoldWriter $writer;

    private StubRenderer $renderer;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->temporaryDirectory = sys_get_temp_dir() . '/frosh-jetpack-makers-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->temporaryDirectory);
        $this->bundle = new ScaffoldTestBundle($this->temporaryDirectory);
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundle')->willReturn($this->bundle);
        $this->bundleResolver = new BundleResolver($kernel);
        $this->writer = new ScaffoldWriter($this->filesystem);
        $this->renderer = new StubRenderer();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->temporaryDirectory);
    }

    public function testMakesEntityWithCanonicalCommandAndLegacyAlias(): void
    {
        $command = new MakeEntityCommand(
            $this->bundleResolver,
            $this->writer,
            new EntityScaffolder($this->renderer),
        );
        $tester = new CommandTester($command);

        static::assertSame('frosh:jetpack:make:entity', $command->getName());
        static::assertContains('frosh:jetpack:entity:make', $command->getAliases());
        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'entity' => 'Review',
            '--table' => 'acme_review_entry',
        ]));

        $directory = $this->temporaryDirectory . '/Entity/Review';
        $files = [
            $directory . '/ReviewEntity.php',
            $directory . '/ReviewCollection.php',
            $directory . '/ReviewDefinition.php',
        ];
        foreach ($files as $file) {
            static::assertFileExists($file);
            static::assertNotEmpty(\PhpToken::tokenize((string) file_get_contents($file), \TOKEN_PARSE));
        }
        static::assertStringContainsString('#[Entity(name: \'acme_review_entry\'', (string) file_get_contents($files[2]));

        static::assertSame(0, $tester->execute(['bundle' => $this->bundle->getName(), 'entity' => 'Review', '--table' => 'acme_review_entry']));
        static::assertStringContainsString('Unchanged', $tester->getDisplay());
    }

    public function testEntityDryRunDoesNotCreateFiles(): void
    {
        $tester = new CommandTester(new MakeEntityCommand(
            $this->bundleResolver,
            $this->writer,
            new EntityScaffolder($this->renderer),
        ));

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'entity' => 'Review',
            '--dry-run' => true,
        ]));

        static::assertDirectoryDoesNotExist($this->temporaryDirectory . '/Entity');
        static::assertStringContainsString('Would create', $tester->getDisplay());
    }

    public function testMakesTimestampedReversibleMigration(): void
    {
        $clock = static::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('@1785000123'));
        $tester = new CommandTester(new MakeMigrationCommand(
            $this->bundleResolver,
            $this->writer,
            new MigrationScaffolder($this->renderer),
            $clock,
        ));

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'migration' => 'AddReviewStatus',
        ]));

        $path = $this->temporaryDirectory . '/Migration/Migration1785000123AddReviewStatus.php';
        static::assertFileExists($path);
        $content = (string) file_get_contents($path);
        static::assertStringContainsString('final class Migration1785000123AddReviewStatus extends Migration', $content);
        static::assertStringContainsString('public function up(Connection $connection): void', $content);
        static::assertStringContainsString('public function down(Connection $connection): void', $content);
        static::assertStringContainsString('return 1785000123;', $content);

        require_once $path;
        $migration = $this->generatedClass(implode('\\', ['Acme', 'Review', 'Migration', 'Migration1785000123AddReviewStatus']))->newInstance();
        static::assertInstanceOf(Migration::class, $migration);
        static::assertSame(1785000123, $migration->getCreationTimestamp());
    }

    public function testMakesOneFileScheduledTaskWithStableName(): void
    {
        $tester = new CommandTester(new MakeScheduledTaskCommand(
            $this->bundleResolver,
            $this->writer,
            new ScheduledTaskScaffolder($this->renderer),
        ));

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'task' => 'CleanupExpiredReviews',
            '--interval' => '300',
            '--name' => 'acme_review.cleanup_expired_reviews',
            '--reschedule-on-failure' => true,
        ]));

        $path = $this->temporaryDirectory . '/ScheduledTask/CleanupExpiredReviews.php';
        static::assertFileExists($path);
        $content = (string) file_get_contents($path);
        static::assertStringContainsString('interval: 300,', $content);
        static::assertStringContainsString('name: \'acme_review.cleanup_expired_reviews\',', $content);
        static::assertStringContainsString('rescheduleOnFailure: true,', $content);
        static::assertStringContainsString('public function __invoke(Context $context): void', $content);

        require_once $path;
        $reflection = $this->generatedClass(implode('\\', ['Acme', 'Review', 'ScheduledTask', 'CleanupExpiredReviews']));
        $attribute = $reflection->getAttributes(AsScheduledTask::class)[0]->newInstance();
        (new ScheduledTaskDeclarationValidator())->validate($reflection, $attribute, $attribute->name ?? '');
    }

    public function testScheduledTaskMakerDerivesExplicitStableName(): void
    {
        $tester = new CommandTester(new MakeScheduledTaskCommand(
            $this->bundleResolver,
            $this->writer,
            new ScheduledTaskScaffolder($this->renderer),
        ));

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'task' => 'ImportURLMappings',
        ]));

        $content = (string) file_get_contents($this->temporaryDirectory . '/ScheduledTask/ImportURLMappings.php');
        static::assertStringContainsString('name: \'scaffold_test_bundle.import_url_mappings\',', $content);
        static::assertStringContainsString('rescheduleOnFailure: false,', $content);
    }

    public function testMakesAutoconfiguredSymfonyCommand(): void
    {
        $tester = new CommandTester(new MakeConsoleCommand(
            $this->bundleResolver,
            $this->writer,
            new CommandScaffolder($this->renderer),
        ));

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'command' => 'RebuildIndex',
            '--name' => 'acme-review:rebuild',
            '--description' => 'Rebuilds Acme\'s review index',
        ]));

        $path = $this->temporaryDirectory . '/Command/RebuildIndexCommand.php';
        static::assertFileExists($path);
        $content = (string) file_get_contents($path);
        static::assertStringContainsString('name: \'acme-review:rebuild\',', $content);
        static::assertStringContainsString('description: \'Rebuilds Acme\\\'s review index\',', $content);
        static::assertStringContainsString('final class RebuildIndexCommand extends Command', $content);
        static::assertStringContainsString('return self::SUCCESS;', $content);

        require_once $path;
        $reflection = $this->generatedClass(implode('\\', ['Acme', 'Review', 'Command', 'RebuildIndexCommand']));
        $attribute = $reflection->getAttributes(AsCommand::class)[0]->newInstance();
        static::assertSame('acme-review:rebuild', $attribute->name);
    }

    public function testMakesEntityBackedCmsElementWithDerivedDefaults(): void
    {
        $tester = $this->cmsElementTester();

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'element' => 'Recipe',
            '--entity' => 'acme_recipe',
        ]));

        $administration = $this->temporaryDirectory . '/Resources/app/administration/src/cms-element/acme-recipe/index.js';
        $snippet = $this->temporaryDirectory . '/Resources/app/administration/src/cms-element/acme-recipe/snippet/en-GB.json';
        $resolver = $this->temporaryDirectory . '/Cms/RecipeCmsElementResolver.php';
        $template = $this->temporaryDirectory . '/Resources/views/storefront/element/cms-element-acme-recipe.html.twig';

        static::assertFileExists($administration);
        static::assertFileExists($snippet);
        static::assertFileExists($resolver);
        static::assertFileExists($template);

        $administrationContent = (string) file_get_contents($administration);
        static::assertStringContainsString("name: 'acme-recipe'", $administrationContent);
        static::assertStringContainsString("entity: 'acme_recipe'", $administrationContent);
        static::assertStringContainsString("labelProperty: 'name'", $administrationContent);

        static::assertSame([
            'acme-recipe' => [
                'cms' => [
                    'label' => 'Recipe',
                    'fields' => ['recipe' => 'Recipe'],
                    'placeholders' => ['recipe' => 'Select Recipe'],
                ],
            ],
        ], json_decode((string) file_get_contents($snippet), true, 512, \JSON_THROW_ON_ERROR));

        $resolverContent = (string) file_get_contents($resolver);
        static::assertNotEmpty(\PhpToken::tokenize($resolverContent, \TOKEN_PARSE));
        static::assertStringContainsString('use Acme\\Review\\Entity\\Recipe\\RecipeDefinition;', $resolverContent);
        static::assertStringContainsString("return 'acme-recipe';", $resolverContent);
        static::assertStringContainsString("private const CONFIG_KEY = 'recipe';", $resolverContent);

        require_once $resolver;
        $reflection = $this->generatedClass('Acme\\Review\\Cms\\RecipeCmsElementResolver');
        $resolverInstance = $reflection->newInstance();
        static::assertInstanceOf(AbstractCmsElementResolver::class, $resolverInstance);
        static::assertSame('acme-recipe', $resolverInstance->getType());

        $templateContent = (string) file_get_contents($template);
        static::assertStringContainsString('{% set cmsEntity = element.data.get(\'recipe\') %}', $templateContent);
        static::assertStringContainsString("{{ cmsEntity['name'] }}", $templateContent);
        $twig = new Environment(new ArrayLoader());
        $twig->parse($twig->tokenize(new Source(
            $templateContent,
            'cms-element-acme-recipe.html.twig',
        )));
        static::assertStringContainsString("import './cms-element/acme-recipe';", $tester->getDisplay());
        static::assertStringContainsString('Resources/app/administration/src/main.js', $tester->getDisplay());

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'element' => 'Recipe',
            '--entity' => 'acme_recipe',
        ]));
        static::assertStringContainsString('Unchanged', $tester->getDisplay());
    }

    public function testCmsElementPropagatesExplicitOptionsAcrossArtifacts(): void
    {
        $tester = $this->cmsElementTester();

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'element' => 'FeaturedRecipe',
            '--entity' => 'vendor_recipe',
            '--name' => 'vendor-featured-recipe',
            '--field' => 'selectedRecipe',
            '--definition' => 'Vendor\\Catalog\\Entity\\Recipe\\RecipeDefinition',
            '--label-property' => 'displayName',
        ]));

        $administration = (string) file_get_contents(
            $this->temporaryDirectory . '/Resources/app/administration/src/cms-element/vendor-featured-recipe/index.js',
        );
        $resolver = (string) file_get_contents($this->temporaryDirectory . '/Cms/FeaturedRecipeCmsElementResolver.php');
        $template = (string) file_get_contents(
            $this->temporaryDirectory . '/Resources/views/storefront/element/cms-element-vendor-featured-recipe.html.twig',
        );
        $snippet = json_decode(
            (string) file_get_contents(
                $this->temporaryDirectory . '/Resources/app/administration/src/cms-element/vendor-featured-recipe/snippet/en-GB.json',
            ),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        static::assertStringContainsString("name: 'vendor-featured-recipe'", $administration);
        static::assertStringContainsString("name: 'selectedRecipe'", $administration);
        static::assertStringContainsString("entity: 'vendor_recipe'", $administration);
        static::assertStringContainsString("labelProperty: 'displayName'", $administration);
        static::assertStringContainsString('use Vendor\\Catalog\\Entity\\Recipe\\RecipeDefinition;', $resolver);
        static::assertStringContainsString("private const CONFIG_KEY = 'selectedRecipe';", $resolver);
        static::assertStringContainsString("return 'vendor-featured-recipe';", $resolver);
        static::assertStringContainsString("{% set cmsEntity = element.data.get('selectedRecipe') %}", $template);
        static::assertStringContainsString("{{ cmsEntity['displayName'] }}", $template);
        static::assertSame('Featured recipe', $snippet['vendor-featured-recipe']['cms']['label']);
        static::assertSame(
            'Featured recipe',
            $snippet['vendor-featured-recipe']['cms']['fields']['selectedRecipe'],
        );
    }

    public function testCmsElementDryRunDoesNotCreateFiles(): void
    {
        $tester = $this->cmsElementTester();

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'element' => 'Recipe',
            '--entity' => 'acme_recipe',
            '--dry-run' => true,
        ]));

        static::assertDirectoryDoesNotExist($this->temporaryDirectory . '/Cms');
        static::assertDirectoryDoesNotExist($this->temporaryDirectory . '/Resources');
        static::assertStringContainsString('Would create', $tester->getDisplay());
        static::assertStringContainsString('cms-element-acme-recipe.html.twig', $tester->getDisplay());
    }

    public function testCmsElementRequiresEntityOption(): void
    {
        $tester = $this->cmsElementTester();

        $this->expectExceptionObject(new \InvalidArgumentException('The --entity option is required.'));

        $tester->execute([
            'bundle' => $this->bundle->getName(),
            'element' => 'Recipe',
        ]);
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('invalidCmsElementOptions')]
    public function testCmsElementRejectsInvalidOptions(array $options, string $message): void
    {
        $tester = $this->cmsElementTester();

        $this->expectExceptionObject(new \InvalidArgumentException($message));

        $tester->execute(array_merge([
            'bundle' => $this->bundle->getName(),
            'element' => 'Recipe',
            '--entity' => 'acme_recipe',
        ], $options));
    }

    /**
     * @return \Generator<string, array{array<string, string>, string}>
     */
    public static function invalidCmsElementOptions(): \Generator
    {
        yield 'DAL entity must use lower snake case' => [
            ['--entity' => 'AcmeRecipe'],
            'The entity name must be lower snake_case.',
        ];

        yield 'technical name must remain namespaced' => [
            ['--name' => 'recipe'],
            'The CMS element name must contain at least two lower-kebab-case segments.',
        ];

        yield 'field must be a safe JavaScript key' => [
            ['--field' => '__proto__'],
            'The CMS field must be a safe lowerCamelCase identifier.',
        ];

        yield 'definition must be fully qualified' => [
            ['--definition' => 'RecipeDefinition'],
            'The entity definition must be a fully-qualified PascalCase class name.',
        ];

        yield 'label property must be a direct key' => [
            ['--label-property' => 'translated.name'],
            'The label property must be a safe lowerCamelCase identifier.',
        ];
    }

    public function testCmsElementNormalizesAcronymsInDerivedNames(): void
    {
        $tester = $this->cmsElementTester();

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'element' => 'URLRecipe',
            '--entity' => 'acme_recipe',
        ]));

        $path = $this->temporaryDirectory . '/Resources/app/administration/src/cms-element/acme-url-recipe/index.js';
        static::assertFileExists($path);
        static::assertStringContainsString("name: 'urlRecipe'", (string) file_get_contents($path));
    }

    public function testSymfonyCommandMakerDerivesNameAndDescription(): void
    {
        $tester = new CommandTester(new MakeConsoleCommand(
            $this->bundleResolver,
            $this->writer,
            new CommandScaffolder($this->renderer),
        ));

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'command' => 'ImportURLMappingsCommand',
        ]));

        $content = (string) file_get_contents($this->temporaryDirectory . '/Command/ImportURLMappingsCommand.php');
        static::assertStringContainsString('name: \'scaffold-test-bundle:import-url-mappings\',', $content);
        static::assertStringContainsString('description: \'Runs the Import url mappings operation\',', $content);
    }

    public function testRejectsInvalidScheduledTaskInterval(): void
    {
        $tester = new CommandTester(new MakeScheduledTaskCommand(
            $this->bundleResolver,
            $this->writer,
            new ScheduledTaskScaffolder($this->renderer),
        ));

        $this->expectExceptionObject(new \InvalidArgumentException('The scheduled-task interval must be at least 1 second.'));

        $tester->execute([
            'bundle' => $this->bundle->getName(),
            'task' => 'CleanupExpiredReviews',
            '--interval' => '0',
        ]);
    }

    private function cmsElementTester(): CommandTester
    {
        return new CommandTester(new MakeCmsElementCommand(
            $this->bundleResolver,
            $this->writer,
            new CmsElementScaffolder($this->renderer),
        ));
    }

    /**
     * @return \ReflectionClass<object>
     */
    private function generatedClass(string $class): \ReflectionClass
    {
        if (!class_exists($class)) {
            throw new \RuntimeException(\sprintf('Generated class "%s" was not loaded.', $class));
        }

        return new \ReflectionClass($class);
    }
}
