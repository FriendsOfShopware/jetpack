<?php declare(strict_types=1);

namespace Frosh\Jetpack\Command;

use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\Scaffolding\ScaffoldPlan;
use Frosh\Jetpack\Scaffolding\ScaffoldWriter;
use Shopware\Core\Framework\Bundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
abstract class AbstractMakeCommand extends Command
{
    public function __construct(
        private readonly BundleResolver $bundleResolver,
        private readonly ScaffoldWriter $writer,
    ) {
        parent::__construct();
    }

    protected function addDryRunOption(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview the complete scaffold without writing files');
    }

    protected function bundle(InputInterface $input): Bundle
    {
        return $this->bundleResolver->resolve($this->requiredStringArgument($input, 'bundle'));
    }

    protected function requiredStringArgument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);
        if (!\is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException(\sprintf('The %s argument must be a non-empty string.', $name));
        }

        return $value;
    }

    protected function optionalStringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);
        if ($value === null) {
            return null;
        }
        if (!\is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException(\sprintf('The --%s option must be a non-empty string.', $name));
        }

        return $value;
    }

    protected function writePlan(ScaffoldPlan $plan, InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $result = $this->writer->write($plan, $dryRun);

        if ($dryRun) {
            $io->success('Dry run completed. No files were written.');
        } elseif ($result->created === []) {
            $io->success('Scaffold is already up to date.');
        } else {
            $io->success($plan->summary);
        }

        if ($result->created !== []) {
            $io->section($dryRun ? 'Would create' : 'Created');
            $io->listing($result->created);
        }
        if ($result->unchanged !== []) {
            $io->section('Unchanged');
            $io->listing($result->unchanged);
        }
        foreach ($plan->notes as $note) {
            $io->note($note);
        }

        return self::SUCCESS;
    }
}
