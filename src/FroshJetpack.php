<?php declare(strict_types=1);

namespace Frosh\Jetpack;

use Doctrine\DBAL\Connection;
use Frosh\Jetpack\Entity\JetpackDefinition;
use Frosh\Jetpack\Migration\DbalMigrationLock;
use Frosh\Jetpack\Migration\DbalMigrationStateStore;
use Frosh\Jetpack\Migration\DefaultMigrationRunner;
use Frosh\Jetpack\Migration\FilesystemMigrationProvider;
use Frosh\Jetpack\Migration\MigrationRunner;
use Frosh\Jetpack\ScheduledTask\ScheduledTaskCompilerPass;
use Frosh\Jetpack\ScheduledTask\ScheduledTaskProxyGenerator;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class FroshJetpack extends Plugin
{
    public function getTemplatePriority(): int
    {
        return -1000;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container
            ->registerForAutoconfiguration(JetpackDefinition::class)
            ->addTag('frosh.jetpack.entity_definition');

        // Run after Symfony resolves FQCN service IDs (priority 100), but before the default-priority
        // passes finalize scheduled-task handlers and tagged iterators.
        $container->addCompilerPass(
            new ScheduledTaskCompilerPass(),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            90,
        );
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->container === null) {
            return;
        }

        $cacheDirectory = $this->container->getParameter('kernel.cache_dir');
        if (!\is_string($cacheDirectory) || $cacheDirectory === '') {
            return;
        }

        $generatedFile = rtrim($cacheDirectory, '/\\') . \DIRECTORY_SEPARATOR . ScheduledTaskProxyGenerator::RELATIVE_FILE;
        if (is_file($generatedFile)) {
            require $generatedFile;
        }
    }

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $this->migrationRunner()->up($this);
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        $this->migrationRunner()->up($this);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->migrationRunner()->down($this);
        $this->connection()->executeStatement('DROP TABLE IF EXISTS `frosh_jetpack_migration`');
    }

    private function migrationRunner(): MigrationRunner
    {
        $connection = $this->connection();

        return new DefaultMigrationRunner(
            new FilesystemMigrationProvider(),
            new DbalMigrationStateStore($connection),
            new DbalMigrationLock($connection),
            $connection,
        );
    }

    private function connection(): Connection
    {
        if ($this->container === null) {
            throw new \LogicException('The plugin container is not available during the Frosh Jetpack lifecycle.');
        }

        $connection = $this->container->get(Connection::class);
        if (!$connection instanceof Connection) {
            throw new \LogicException('The database connection is not available during the Frosh Jetpack lifecycle.');
        }

        return $connection;
    }
}
