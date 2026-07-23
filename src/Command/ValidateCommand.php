<?php declare(strict_types=1);

namespace Frosh\Jetpack\Command;

use Frosh\Jetpack\Configuration\BundleReference;
use Frosh\Jetpack\Configuration\ConfigurationDefinitionRegistry;
use Frosh\Jetpack\Configuration\ConfigurationException;
use Frosh\Jetpack\CustomField\CustomFieldException;
use Frosh\Jetpack\CustomField\YamlCustomFieldLoader;
use Frosh\Jetpack\Entity\BundleEntitySchemaProvider;
use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\Entity\EntitySchemaException;
use Frosh\Jetpack\MailTemplate\MailTemplateException;
use Frosh\Jetpack\MailTemplate\YamlMailTemplateLoader;
use Frosh\Jetpack\Migration\MigrationException;
use Frosh\Jetpack\Migration\MigrationProvider;
use Frosh\Jetpack\ScheduledTask\ScheduledTaskRegistry;
use Shopware\Core\Framework\Bundle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(name: 'frosh:jetpack:validate', description: 'Validate all Jetpack definitions')]
final class ValidateCommand extends Command
{
    private const CONFIGURATION = 'Configuration';
    private const CUSTOM_FIELDS = 'Custom fields';
    private const MAIL_TEMPLATES = 'Mail templates';
    private const ENTITIES = 'Entities';
    private const MIGRATIONS = 'Migrations';
    private const SCHEDULED_TASKS = 'Scheduled tasks';

    public function __construct(
        private readonly BundleResolver $bundleResolver,
        private readonly ConfigurationDefinitionRegistry $configurationDefinitions,
        private readonly YamlCustomFieldLoader $customFieldLoader,
        private readonly YamlMailTemplateLoader $mailTemplateLoader,
        private readonly BundleEntitySchemaProvider $entitySchemas,
        private readonly MigrationProvider $migrations,
        private readonly ScheduledTaskRegistry $scheduledTasks,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('bundle', InputArgument::OPTIONAL, 'Optional Shopware bundle name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('bundle');

        try {
            $bundles = \is_string($name) && $name !== ''
                ? [$this->bundleResolver->resolve($name)]
                : $this->bundleResolver->all();
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return self::FAILURE;
        }

        $errors = [];
        $errorCounts = [];
        [$configurationFiles, $configurationFields] = $this->validateConfiguration($bundles, $errors, $errorCounts);
        [$customFieldFiles, $customFieldSets, $customFields] = $this->validateCustomFields($bundles, $errors, $errorCounts);
        [$mailTemplateFiles, $mailTemplates, $mailTemplateTranslations] = $this->validateMailTemplates($bundles, $errors, $errorCounts);
        $entities = $this->validateEntities($bundles, \is_string($name) && $name !== '', $errors, $errorCounts);
        $migrations = $this->validateMigrations($bundles, $errors, $errorCounts);
        $scheduledTasks = $this->validateScheduledTasks($bundles, \is_string($name) && $name !== '');

        $io->table(['Feature', 'Definitions', 'Status'], [
            [self::CONFIGURATION, \sprintf('%s, %s', $this->count($configurationFiles, 'YAML file'), $this->count($configurationFields, 'field')), $this->status(self::CONFIGURATION, $errorCounts)],
            [self::CUSTOM_FIELDS, \sprintf('%s, %s, %s', $this->count($customFieldFiles, 'YAML file'), $this->count($customFieldSets, 'set'), $this->count($customFields, 'field')), $this->status(self::CUSTOM_FIELDS, $errorCounts)],
            [self::MAIL_TEMPLATES, \sprintf('%s, %s, %s', $this->count($mailTemplateFiles, 'YAML file'), $this->count($mailTemplates, 'template'), $this->count($mailTemplateTranslations, 'translation')), $this->status(self::MAIL_TEMPLATES, $errorCounts)],
            [self::ENTITIES, $this->count($entities, 'entity'), $this->status(self::ENTITIES, $errorCounts)],
            [self::MIGRATIONS, $this->count($migrations, 'migration'), $this->status(self::MIGRATIONS, $errorCounts)],
            [self::SCHEDULED_TASKS, $this->count($scheduledTasks, 'scheduled task'), $this->status(self::SCHEDULED_TASKS, $errorCounts)],
        ]);

        if ($errors !== []) {
            $io->error($errors);

            return self::FAILURE;
        }

        $io->success(\is_string($name) && $name !== ''
            ? \sprintf('All Jetpack definitions for bundle "%s" are valid.', $name)
            : 'All Jetpack definitions are valid.');

        return self::SUCCESS;
    }

    /**
     * @param list<Bundle> $bundles
     * @param list<string> $errors
     * @param array<string, int> $errorCounts
     *
     * @return array{int, int}
     */
    private function validateConfiguration(array $bundles, array &$errors, array &$errorCounts): array
    {
        $files = 0;
        $fields = 0;

        foreach ($bundles as $bundle) {
            if (!is_file($bundle->getPath() . BundleReference::RELATIVE_PATH)) {
                continue;
            }
            ++$files;

            try {
                $fields += \count($this->configurationDefinitions->get($bundle->getName())->fields);
            } catch (ConfigurationException $exception) {
                $this->recordError(self::CONFIGURATION, $bundle, $exception, $errors, $errorCounts);
            }
        }

        return [$files, $fields];
    }

    /**
     * @param list<Bundle> $bundles
     * @param list<string> $errors
     * @param array<string, int> $errorCounts
     *
     * @return array{int, int, int}
     */
    private function validateCustomFields(array $bundles, array &$errors, array &$errorCounts): array
    {
        $files = 0;
        $sets = 0;
        $fields = 0;

        foreach ($bundles as $bundle) {
            if (!is_file($bundle->getPath() . YamlCustomFieldLoader::RELATIVE_PATH)) {
                continue;
            }
            ++$files;

            try {
                $definition = $this->customFieldLoader->load($bundle);
                $sets += \count($definition->sets);
                $fields += array_sum(array_map(
                    static fn (array $set): int => \count($set['fields']),
                    $definition->sets,
                ));
            } catch (CustomFieldException $exception) {
                $this->recordError(self::CUSTOM_FIELDS, $bundle, $exception, $errors, $errorCounts);
            }
        }

        return [$files, $sets, $fields];
    }

    /**
     * @param list<Bundle> $bundles
     * @param list<string> $errors
     * @param array<string, int> $errorCounts
     *
     * @return array{int, int, int}
     */
    private function validateMailTemplates(array $bundles, array &$errors, array &$errorCounts): array
    {
        $files = 0;
        $templates = 0;
        $translations = 0;
        $technicalNames = [];

        foreach ($bundles as $bundle) {
            if (!is_file($bundle->getPath() . YamlMailTemplateLoader::RELATIVE_PATH)) {
                continue;
            }
            ++$files;

            try {
                $manifest = $this->mailTemplateLoader->load($bundle);
                foreach ($manifest->templates as $template) {
                    $owner = $technicalNames[$template['technicalName']] ?? null;
                    if ($owner !== null) {
                        throw MailTemplateException::invalidDefinition(
                            $manifest->path,
                            \sprintf(
                                'Technical name "%s" is already declared by bundle "%s".',
                                $template['technicalName'],
                                $owner,
                            ),
                        );
                    }
                    $technicalNames[$template['technicalName']] = $bundle->getName();
                    ++$templates;
                    $translations += \count($template['translations']);
                }
            } catch (MailTemplateException $exception) {
                $this->recordError(self::MAIL_TEMPLATES, $bundle, $exception, $errors, $errorCounts);
            }
        }

        return [$files, $templates, $translations];
    }

    /**
     * @param list<Bundle> $bundles
     * @param list<string> $errors
     * @param array<string, int> $errorCounts
     */
    private function validateEntities(array $bundles, bool $bundleWasSelected, array &$errors, array &$errorCounts): int
    {
        try {
            if (!$bundleWasSelected) {
                return \count($this->entitySchemas->all());
            }

            \assert(isset($bundles[0]));

            return \count($this->entitySchemas->forBundle($bundles[0])->entities);
        } catch (EntitySchemaException $exception) {
            $bundle = $bundleWasSelected ? $bundles[0] : null;
            $this->recordError(self::ENTITIES, $bundle, $exception, $errors, $errorCounts);

            return 0;
        }
    }

    /**
     * @param list<Bundle> $bundles
     * @param list<string> $errors
     * @param array<string, int> $errorCounts
     */
    private function validateMigrations(array $bundles, array &$errors, array &$errorCounts): int
    {
        $migrations = 0;

        foreach ($bundles as $bundle) {
            try {
                $migrations += \count($this->migrations->forBundle($bundle));
            } catch (MigrationException $exception) {
                $this->recordError(self::MIGRATIONS, $bundle, $exception, $errors, $errorCounts);
            }
        }

        return $migrations;
    }

    /**
     * @param list<Bundle> $bundles
     */
    private function validateScheduledTasks(array $bundles, bool $bundleWasSelected): int
    {
        if (!$bundleWasSelected) {
            return \count($this->scheduledTasks->all());
        }

        \assert(isset($bundles[0]));

        return \count($this->scheduledTasks->forBundle($bundles[0]));
    }

    /**
     * @param list<string> $errors
     * @param array<string, int> $errorCounts
     */
    private function recordError(
        string $feature,
        ?Bundle $bundle,
        \RuntimeException $exception,
        array &$errors,
        array &$errorCounts,
    ): void {
        $errorCounts[$feature] = ($errorCounts[$feature] ?? 0) + 1;
        $errors[] = \sprintf(
            '%s%s: %s',
            $feature,
            $bundle instanceof Bundle ? \sprintf(' (%s)', $bundle->getName()) : '',
            $exception->getMessage(),
        );
    }

    /**
     * @param array<string, int> $errorCounts
     */
    private function status(string $feature, array $errorCounts): string
    {
        $errors = $errorCounts[$feature] ?? 0;

        return $errors === 0 ? 'Valid' : \sprintf('Invalid (%s)', $this->count($errors, 'error'));
    }

    private function count(int $count, string $singular): string
    {
        return \sprintf('%d %s%s', $count, $singular, $count === 1 ? '' : 's');
    }
}
