<?php declare(strict_types=1);

namespace Frosh\Jetpack\Command;

use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\Scaffolding\Generator\ScheduledTaskScaffolder;
use Frosh\Jetpack\Scaffolding\ScaffoldWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(name: 'frosh:jetpack:make:scheduled-task', description: 'Scaffolds a one-class attributed scheduled task')]
final class MakeScheduledTaskCommand extends AbstractMakeCommand
{
    public function __construct(
        BundleResolver $bundleResolver,
        ScaffoldWriter $writer,
        private readonly ScheduledTaskScaffolder $scaffolder,
    ) {
        parent::__construct($bundleResolver, $writer);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('bundle', InputArgument::REQUIRED, 'Shopware bundle name')
            ->addArgument('task', InputArgument::REQUIRED, 'Scheduled-task class name in PascalCase')
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Default interval in seconds', '300')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Explicit stable scheduled-task name')
            ->addOption('reschedule-on-failure', null, InputOption::VALUE_NONE, 'Reschedule the task after a failed execution');
        $this->addDryRunOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $interval = $input->getOption('interval');
        if (!\is_int($interval) && (!\is_string($interval) || preg_match('/^\d+$/', $interval) !== 1)) {
            throw new \InvalidArgumentException('The --interval option must be a whole number of seconds.');
        }

        return $this->writePlan(
            $this->scaffolder->create(
                $this->bundle($input),
                $this->requiredStringArgument($input, 'task'),
                (int) $interval,
                $this->optionalStringOption($input, 'name'),
                (bool) $input->getOption('reschedule-on-failure'),
            ),
            $input,
            $output,
        );
    }
}
