<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Command;

use Frosh\Jetpack\Command\ValidateCommand;
use Frosh\Jetpack\Configuration\BundleConfigurationLocator;
use Frosh\Jetpack\Configuration\ConfigurationDefinitionRegistry;
use Frosh\Jetpack\Configuration\ValueTypeValidator;
use Frosh\Jetpack\Configuration\YamlConfigurationLoader;
use Frosh\Jetpack\CustomField\YamlCustomFieldLoader;
use Frosh\Jetpack\Entity\BundleEntitySchemaProvider;
use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\MailTemplate\YamlMailTemplateLoader;
use Frosh\Jetpack\Migration\FilesystemMigrationProvider;
use Frosh\Jetpack\ScheduledTask\ScheduledTaskRegistry;
use Frosh\Jetpack\Tests\Fixture\CustomFieldTestBundle;
use Frosh\Jetpack\Tests\Fixture\InvalidCustomFieldSchemaBundle;
use Frosh\Jetpack\Tests\Fixture\InvalidMailTemplateBundle;
use Frosh\Jetpack\Tests\Fixture\MailTemplateTestBundle;
use Frosh\Jetpack\Tests\Fixture\MigrationTestBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(ValidateCommand::class)]
final class ValidateCommandTest extends TestCase
{
    public function testValidatesEveryJetpackFeatureWithOneCommand(): void
    {
        $tester = $this->tester(new CustomFieldTestBundle(), new MailTemplateTestBundle(), new MigrationTestBundle());

        $status = $tester->execute([]);
        $display = $tester->getDisplay();

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('Configuration', $display);
        static::assertStringContainsString('Custom fields', $display);
        static::assertStringContainsString('Mail templates', $display);
        static::assertStringContainsString('Entities', $display);
        static::assertStringContainsString('Migrations', $display);
        static::assertStringContainsString('Scheduled tasks', $display);
        static::assertStringContainsString('1 YAML file, 1 field', $display);
        static::assertStringContainsString('1 YAML file, 1 set, 3 fields', $display);
        static::assertStringContainsString('1 YAML file, 1 template, 2 translations', $display);
        static::assertStringContainsString('2 migrations', $display);
        static::assertStringContainsString('0 scheduled tasks', $display);
        static::assertStringContainsString('All Jetpack definitions are valid.', $display);
    }

    public function testReportsErrorsFromAllFeaturesTogether(): void
    {
        $bundle = new InvalidCustomFieldSchemaBundle();
        $tester = $this->tester($bundle);

        $status = $tester->execute(['bundle' => $bundle->getName()]);
        $display = $tester->getDisplay();

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString('Configuration (InvalidCustomFieldSchemaBundle)', $display);
        static::assertStringContainsString('Custom fields (InvalidCustomFieldSchemaBundle)', $display);
        static::assertStringContainsString('Invalid (1 error)', $display);
    }

    public function testReportsInvalidMailTemplatesThroughTheUnifiedCommand(): void
    {
        $bundle = new InvalidMailTemplateBundle();
        $tester = $this->tester($bundle);

        $status = $tester->execute(['bundle' => $bundle->getName()]);

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString('Mail templates (InvalidMailTemplateBundle)', $tester->getDisplay());
        static::assertStringContainsString('unknown_entity', $tester->getDisplay());
    }

    private function tester(Bundle ...$bundles): CommandTester
    {
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundles')->willReturn($bundles);
        $kernel->method('getBundle')->willReturnCallback(static function (string $name) use ($bundles): Bundle {
            foreach ($bundles as $bundle) {
                if ($bundle->getName() === $name) {
                    return $bundle;
                }
            }

            throw new \InvalidArgumentException(\sprintf('Bundle "%s" does not exist.', $name));
        });

        $bundleResolver = new BundleResolver($kernel);
        $definitions = new ConfigurationDefinitionRegistry(
            new BundleConfigurationLocator($kernel),
            new YamlConfigurationLoader(new ValueTypeValidator()),
        );
        $entityDefinitions = static::createStub(DefinitionInstanceRegistry::class);
        $entityDefinitions->method('has')->willReturnCallback(
            static fn (string $entityName): bool => \in_array($entityName, ['order', 'sales_channel'], true),
        );

        return new CommandTester(new ValidateCommand(
            $bundleResolver,
            $definitions,
            new YamlCustomFieldLoader(),
            new YamlMailTemplateLoader(new Environment(new ArrayLoader()), $entityDefinitions),
            new BundleEntitySchemaProvider([]),
            new FilesystemMigrationProvider(),
            new ScheduledTaskRegistry([]),
        ));
    }
}
