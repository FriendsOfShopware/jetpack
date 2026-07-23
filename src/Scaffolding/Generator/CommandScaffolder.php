<?php declare(strict_types=1);

namespace Frosh\Jetpack\Scaffolding\Generator;

use Frosh\Jetpack\Scaffolding\GeneratedFile;
use Frosh\Jetpack\Scaffolding\ScaffoldNaming;
use Frosh\Jetpack\Scaffolding\ScaffoldPlan;
use Frosh\Jetpack\Scaffolding\StubRenderer;
use Shopware\Core\Framework\Bundle;

/**
 * @internal
 */
final class CommandScaffolder
{
    private const COMMAND_NAME_PATTERN = '/^[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)+$/';

    public function __construct(private readonly StubRenderer $renderer)
    {
    }

    public function create(Bundle $bundle, string $name, ?string $commandName, ?string $description): ScaffoldPlan
    {
        ScaffoldNaming::assertPascalCase($name, 'command');
        $operation = str_ends_with($name, 'Command') ? substr($name, 0, -7) : $name;
        if ($operation === '') {
            throw new \InvalidArgumentException('The command name must contain a class prefix before "Command".');
        }
        $class = $operation . 'Command';

        $commandName ??= str_replace('_', '-', $bundle->getContainerPrefix()) . ':' . ScaffoldNaming::kebabCase($operation);
        if (mb_strlen($commandName) > 255 || preg_match(self::COMMAND_NAME_PATTERN, $commandName) !== 1) {
            throw new \InvalidArgumentException('The Symfony command name must contain at least two lower-case colon-separated segments and be at most 255 characters.');
        }
        $description ??= 'Runs the ' . ScaffoldNaming::humanize($operation) . ' operation';

        $namespace = trim($bundle->getNamespace(), '\\') . '\\Command';
        $content = $this->renderer->render(<<<'PHP'
<?php declare(strict_types=1);

namespace %jetpack.namespace%;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: %jetpack.command_name%,
    description: %jetpack.description%,
)]
final class %jetpack.class% extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // TODO: Implement the command.

        return self::SUCCESS;
    }
}

PHP, [
            'namespace' => $namespace,
            'command_name' => var_export($commandName, true),
            'description' => var_export($description, true),
            'class' => $class,
        ]);

        return new ScaffoldPlan(
            $bundle->getPath(),
            [new GeneratedFile('Command/' . $class . '.php', $content)],
            \sprintf('Created Symfony command %s.', $class),
            ['Ensure the bundle autowires and autoconfigures the generated command.'],
        );
    }
}
