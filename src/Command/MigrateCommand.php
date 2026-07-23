<?php declare(strict_types=1);

namespace Frosh\Jetpack\Command;

use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\Migration\MigrationRunner;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(name: 'frosh:jetpack:migrate', description: 'Run pending Jetpack migrations for active Shopware plugins')]
final class MigrateCommand extends Command
{
    public function __construct(
        private readonly BundleResolver $bundleResolver,
        private readonly MigrationRunner $migrationRunner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('plugin', InputArgument::OPTIONAL, 'Active Shopware plugin name');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Run pending migrations for all active Shopware plugins');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $argument = $input->getArgument('plugin');
        $pluginName = \is_string($argument) ? trim($argument) : '';
        $all = $input->getOption('all') === true;

        if ($pluginName === '' && !$all) {
            $io->error('Pass a plugin name or use --all.');

            return self::INVALID;
        }
        if ($pluginName !== '' && $all) {
            $io->error('Plugin name and --all cannot be used together.');

            return self::INVALID;
        }

        try {
            $plugins = $all ? $this->activePlugins() : [$this->plugin($pluginName)];
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return self::INVALID;
        }

        if ($plugins === []) {
            $io->success('No active Shopware plugins found.');

            return self::SUCCESS;
        }

        $appliedMigrations = 0;
        $changedPlugins = 0;
        foreach ($plugins as $plugin) {
            $io->section($plugin->getName());
            $applied = $this->migrationRunner->up($plugin);
            if ($applied === []) {
                $io->text('No pending Jetpack migrations.');

                continue;
            }

            $io->listing($applied);
            $appliedMigrations += \count($applied);
            ++$changedPlugins;
        }

        if (!$all) {
            $plugin = $plugins[0];
            if ($appliedMigrations === 0) {
                $io->success(\sprintf('No pending Jetpack migrations for plugin "%s".', $plugin->getName()));

                return self::SUCCESS;
            }

            $io->success(\sprintf(
                'Applied %d Jetpack migration%s for plugin "%s".',
                $appliedMigrations,
                $appliedMigrations === 1 ? '' : 's',
                $plugin->getName(),
            ));

            return self::SUCCESS;
        }

        $pluginCount = \count($plugins);
        if ($appliedMigrations === 0) {
            $io->success(\sprintf(
                'No pending Jetpack migrations for %d active plugin%s.',
                $pluginCount,
                $pluginCount === 1 ? '' : 's',
            ));

            return self::SUCCESS;
        }

        $io->success(\sprintf(
            'Applied %d Jetpack migration%s across %d of %d active plugin%s.',
            $appliedMigrations,
            $appliedMigrations === 1 ? '' : 's',
            $changedPlugins,
            $pluginCount,
            $pluginCount === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<Plugin>
     */
    private function activePlugins(): array
    {
        return array_values(array_filter(
            $this->bundleResolver->all(),
            static fn (Bundle $bundle): bool => $bundle instanceof Plugin,
        ));
    }

    private function plugin(string $name): Plugin
    {
        $bundle = $this->bundleResolver->resolve($name);
        if (!$bundle instanceof Plugin) {
            throw new \InvalidArgumentException(\sprintf(
                'Bundle "%s" is not an active Shopware plugin.',
                $name,
            ));
        }

        return $bundle;
    }
}
