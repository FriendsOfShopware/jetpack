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
final class MigrationScaffolder
{
    public function __construct(private readonly StubRenderer $renderer)
    {
    }

    public function create(Bundle $bundle, string $name, int $timestamp): ScaffoldPlan
    {
        ScaffoldNaming::assertPascalCase($name, 'migration');
        if ($timestamp < 1) {
            throw new \InvalidArgumentException('The migration timestamp must be positive.');
        }

        $class = 'Migration' . $timestamp . $name;
        $this->assertTimestampAvailable($bundle->getMigrationPath(), $timestamp, $class . '.php');

        $content = $this->renderer->render(<<<'PHP'
<?php declare(strict_types=1);

namespace %jetpack.namespace%;

use Doctrine\DBAL\Connection;
use Frosh\Jetpack\Migration\Migration;

final class %jetpack.class% extends Migration
{
    public function getCreationTimestamp(): int
    {
        return %jetpack.timestamp%;
    }

    public function up(Connection $connection): void
    {
        // TODO: Implement the forward migration.
    }

    public function down(Connection $connection): void
    {
        // TODO: Revert the forward migration.
    }
}

PHP, [
            'namespace' => $bundle->getMigrationNamespace(),
            'class' => $class,
            'timestamp' => (string) $timestamp,
        ]);

        return new ScaffoldPlan(
            $bundle->getMigrationPath(),
            [new GeneratedFile($class . '.php', $content)],
            \sprintf('Created reversible migration %s.', $class),
            ['Implement both up() and down(), then run frosh:jetpack:validate ' . $bundle->getName() . '.'],
        );
    }

    private function assertTimestampAvailable(string $directory, int $timestamp, string $expectedFile): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if ($entries === false) {
            throw new \RuntimeException(\sprintf('Cannot read migration directory "%s".', $directory));
        }

        $prefix = 'Migration' . $timestamp;
        foreach ($entries as $entry) {
            if ($entry !== $expectedFile && str_starts_with($entry, $prefix) && str_ends_with($entry, '.php')) {
                throw new \RuntimeException(\sprintf(
                    'Migration timestamp %d is already used by "%s". Retry after the clock advances.',
                    $timestamp,
                    $entry,
                ));
            }
        }
    }
}
