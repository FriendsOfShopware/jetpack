<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\DependencyInjection;

use Doctrine\DBAL\Connection;
use Frosh\Jetpack\Administration\AdministrationAssetMode;
use Frosh\Jetpack\Api\ConfigurationController;
use Frosh\Jetpack\Command\BaselineEntitySchemaCommand;
use Frosh\Jetpack\Command\CheckEntitySchemaCommand;
use Frosh\Jetpack\Command\DiffEntitySchemaCommand;
use Frosh\Jetpack\Command\GenerateEntityMigrationCommand;
use Frosh\Jetpack\Command\MakeCmsElementCommand;
use Frosh\Jetpack\Command\MakeConsoleCommand;
use Frosh\Jetpack\Command\MakeEntityCommand;
use Frosh\Jetpack\Command\MakeMigrationCommand;
use Frosh\Jetpack\Command\MakeScheduledTaskCommand;
use Frosh\Jetpack\Command\MigrateCommand;
use Frosh\Jetpack\Command\ValidateCommand;
use Frosh\Jetpack\Configuration\ConfigurationService;
use Frosh\Jetpack\CustomField\CustomFieldLifecycleSubscriber;
use Frosh\Jetpack\Entity\Migration\EntityMigrationCoordinator;
use Frosh\Jetpack\MailTemplate\MailTemplateLanguageSubscriber;
use Frosh\Jetpack\MailTemplate\MailTemplateLifecycleSubscriber;
use Frosh\Jetpack\MailTemplate\MailTemplateRegistry;
use Frosh\Jetpack\Migration\MigrationLifecycleSubscriber;
use Frosh\Jetpack\Migration\MigrationRunner;
use Frosh\Jetpack\ScheduledTask\ScheduledTaskRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

final class ServiceConfigurationTest extends TestCase
{
    public function testServiceDefinitionsCompile(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.shopware_version', '6.7.0.0');
        $container->register('kernel', KernelInterface::class)->setSynthetic(true);
        $container->register(Connection::class, Connection::class)->setSynthetic(true);
        $container->register(ClockInterface::class, ClockInterface::class)->setSynthetic(true);
        $container->register('validator', ValidatorInterface::class)->setSynthetic(true);
        $container->register(DefinitionInstanceRegistry::class, DefinitionInstanceRegistry::class)->setSynthetic(true);
        $container->register('custom_field_set.repository')->setSynthetic(true);
        $container->register('custom_field_set_relation.repository')->setSynthetic(true);
        $container->register('custom_field.repository')->setSynthetic(true);
        $container->register('mail_template_type.repository')->setSynthetic(true);
        $container->register('mail_template.repository')->setSynthetic(true);
        $container->register('mail_template_type_translation.repository')->setSynthetic(true);
        $container->register('mail_template_translation.repository')->setSynthetic(true);
        $container->register('twig', Environment::class)->setSynthetic(true);
        $container->register('logger', LoggerInterface::class)->setSynthetic(true);

        (new YamlFileLoader(
            $container,
            new FileLocator(\dirname(__DIR__, 3) . '/src/Resources/config'),
        ))->load('services.yaml');

        static::assertTrue($container->getDefinition(ValidateCommand::class)->hasTag('console.command'));
        static::assertTrue($container->hasDefinition(ScheduledTaskRegistry::class));
        foreach ([
            BaselineEntitySchemaCommand::class,
            CheckEntitySchemaCommand::class,
            DiffEntitySchemaCommand::class,
            GenerateEntityMigrationCommand::class,
            MakeCmsElementCommand::class,
            MakeConsoleCommand::class,
            MakeEntityCommand::class,
            MakeMigrationCommand::class,
            MakeScheduledTaskCommand::class,
            MigrateCommand::class,
        ] as $command) {
            static::assertTrue($container->getDefinition($command)->hasTag('console.command'));
        }
        $migrationArguments = array_map(static fn ($argument): string => (string) $argument, $container->getDefinition(EntityMigrationCoordinator::class)->getArguments());
        static::assertNotContains(Connection::class, $migrationArguments);
        static::assertTrue($container->getDefinition(MigrationLifecycleSubscriber::class)->hasTag('kernel.event_subscriber'));
        static::assertTrue($container->getDefinition(CustomFieldLifecycleSubscriber::class)->hasTag('kernel.event_subscriber'));
        static::assertTrue($container->getDefinition(MailTemplateLifecycleSubscriber::class)->hasTag('kernel.event_subscriber'));
        static::assertTrue($container->getDefinition(MailTemplateLanguageSubscriber::class)->hasTag('kernel.event_subscriber'));
        static::assertTrue($container->getDefinition(AdministrationAssetMode::class)->hasTag('twig.extension'));
        $container->compile();

        static::assertTrue($container->has(ConfigurationService::class));
        static::assertTrue($container->has(ConfigurationController::class));
        static::assertTrue($container->has(MigrationRunner::class));
        static::assertTrue($container->has(MailTemplateRegistry::class));
    }
}
