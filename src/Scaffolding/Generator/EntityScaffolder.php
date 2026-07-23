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
final class EntityScaffolder
{
    public function __construct(private readonly StubRenderer $renderer)
    {
    }

    public function create(Bundle $bundle, string $name, ?string $table): ScaffoldPlan
    {
        ScaffoldNaming::assertPascalCase($name, 'entity');
        $table ??= $bundle->getContainerPrefix() . '_' . ScaffoldNaming::snakeCase($name);
        ScaffoldNaming::assertLowerSnakeCase($table, 'table');

        $namespace = trim($bundle->getNamespace(), '\\') . '\\Entity\\' . $name;
        $directory = 'Entity/' . $name . '/';

        return new ScaffoldPlan(
            $bundle->getPath(),
            [
                new GeneratedFile($directory . $name . 'Entity.php', $this->entity($namespace, $name)),
                new GeneratedFile($directory . $name . 'Collection.php', $this->collection($namespace, $name)),
                new GeneratedFile($directory . $name . 'Definition.php', $this->definition($namespace, $name, $table)),
            ],
            \sprintf('Created %s, its concrete definition, and typed collection.', $name),
            ['Ensure the bundle loads these classes as autoconfigured services, then run frosh:jetpack:entity:generate.'],
        );
    }

    private function entity(string $namespace, string $name): string
    {
        return $this->renderer->render(<<<'PHP'
<?php declare(strict_types=1);

namespace %jetpack.namespace%;

use Frosh\Jetpack\Attribute\Id;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

final class %jetpack.name%Entity extends Entity
{
    #[Id]
    public string $id;
}

PHP, ['namespace' => $namespace, 'name' => $name]);
    }

    private function collection(string $namespace, string $name): string
    {
        return $this->renderer->render(<<<'PHP'
<?php declare(strict_types=1);

namespace %jetpack.namespace%;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<%jetpack.name%Entity>
 */
final class %jetpack.name%Collection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return %jetpack.name%Entity::class;
    }
}

PHP, ['namespace' => $namespace, 'name' => $name]);
    }

    private function definition(string $namespace, string $name, string $table): string
    {
        return $this->renderer->render(<<<'PHP'
<?php declare(strict_types=1);

namespace %jetpack.namespace%;

use Frosh\Jetpack\Attribute\Entity;
use Frosh\Jetpack\Entity\JetpackEntityDefinition;

#[Entity(name: %jetpack.table%, entity: %jetpack.name%Entity::class, collection: %jetpack.name%Collection::class)]
final class %jetpack.name%Definition extends JetpackEntityDefinition
{
}

PHP, [
            'namespace' => $namespace,
            'table' => var_export($table, true),
            'name' => $name,
        ]);
    }
}
