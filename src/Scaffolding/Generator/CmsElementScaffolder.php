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
final class CmsElementScaffolder
{
    private const ELEMENT_NAME_PATTERN = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)+$/';

    private const FIELD_NAME_PATTERN = '/^[a-z][A-Za-z0-9]*$/';

    private const DEFINITION_CLASS_PATTERN = '/^[A-Z][A-Za-z0-9]*(?:\\\\[A-Z][A-Za-z0-9]*)+$/';

    private const RESERVED_FIELD_NAMES = ['__proto__', 'constructor', 'prototype'];

    public function __construct(private readonly StubRenderer $renderer)
    {
    }

    public function create(
        Bundle $bundle,
        string $element,
        string $entity,
        ?string $name,
        ?string $field,
        ?string $definition,
        string $labelProperty,
    ): ScaffoldPlan {
        ScaffoldNaming::assertPascalCase($element, 'CMS element');
        ScaffoldNaming::assertLowerSnakeCase($entity, 'entity');

        $name ??= $this->defaultElementName($bundle, $element);
        if (preg_match(self::ELEMENT_NAME_PATTERN, $name) !== 1) {
            throw new \InvalidArgumentException('The CMS element name must contain at least two lower-kebab-case segments.');
        }

        $field ??= $this->lowerCamelCase($element);
        $this->assertFieldName($field, 'CMS field');
        $this->assertFieldName($labelProperty, 'label property');

        $namespace = trim($bundle->getNamespace(), '\\');
        $definition ??= $namespace . '\\Entity\\' . $element . '\\' . $element . 'Definition';
        if (preg_match(self::DEFINITION_CLASS_PATTERN, $definition) !== 1) {
            throw new \InvalidArgumentException('The entity definition must be a fully-qualified PascalCase class name.');
        }

        $humanName = ScaffoldNaming::humanize($element);
        $administrationDirectory = 'Resources/app/administration/src/cms-element/' . $name . '/';

        return new ScaffoldPlan(
            $bundle->getPath(),
            [
                new GeneratedFile($administrationDirectory . 'index.js', $this->administration(
                    $name,
                    $field,
                    $entity,
                    $labelProperty,
                )),
                new GeneratedFile($administrationDirectory . 'snippet/en-GB.json', $this->administrationSnippet(
                    $name,
                    $field,
                    $humanName,
                )),
                new GeneratedFile('Cms/' . $element . 'CmsElementResolver.php', $this->resolver(
                    $namespace . '\\Cms',
                    $element,
                    $name,
                    $field,
                    $definition,
                )),
                new GeneratedFile(
                    'Resources/views/storefront/element/cms-element-' . $name . '.html.twig',
                    $this->storefrontTemplate($name, $field, $labelProperty),
                ),
            ],
            \sprintf('Created the entity-backed CMS element %s.', $name),
            [
                \sprintf(
                    "Add \"import './cms-element/%s';\" to Resources/app/administration/src/main.js.",
                    $name,
                ),
                'Ensure the bundle autowires and autoconfigures the generated resolver.',
                \sprintf(
                    'Ensure administrators can read %s and %s is exposed to the Admin API.',
                    $entity,
                    $labelProperty,
                ),
                'Customize the starter Twig markup, then rebuild the Administration and compile the Storefront theme.',
            ],
        );
    }

    private function defaultElementName(Bundle $bundle, string $element): string
    {
        $namespace = explode('\\', trim($bundle->getNamespace(), '\\'));
        $vendor = $namespace[0];
        ScaffoldNaming::assertPascalCase($vendor, 'bundle root namespace');

        return ScaffoldNaming::kebabCase($vendor) . '-' . ScaffoldNaming::kebabCase($element);
    }

    private function lowerCamelCase(string $value): string
    {
        $segments = explode('_', ScaffoldNaming::snakeCase($value));
        $first = array_shift($segments);

        return $first . implode('', array_map(ucfirst(...), $segments));
    }

    private function assertFieldName(string $name, string $subject): void
    {
        if (
            preg_match(self::FIELD_NAME_PATTERN, $name) !== 1
            || \in_array($name, self::RESERVED_FIELD_NAMES, true)
        ) {
            throw new \InvalidArgumentException(\sprintf(
                'The %s must be a safe lowerCamelCase identifier.',
                $subject,
            ));
        }
    }

    private function administration(
        string $name,
        string $field,
        string $entity,
        string $labelProperty,
    ): string {
        return $this->renderer->render(<<<'JS'
import enGB from './snippet/en-GB.json';

Shopware.Locale.extend('en-GB', enGB);

FroshJetpack.Admin.Cms.register({
    apiVersion: 1,
    name: %jetpack.name%,
    labelSnippet: %jetpack.label_snippet%,
    fields: [
        {
            name: %jetpack.field%,
            type: 'entity-select',
            entity: %jetpack.entity%,
            labelProperty: %jetpack.label_property%,
            labelSnippet: %jetpack.field_label_snippet%,
            placeholderSnippet: %jetpack.field_placeholder_snippet%,
            required: true,
        },
    ],
});

JS, [
            'name' => var_export($name, true),
            'label_snippet' => var_export($name . '.cms.label', true),
            'field' => var_export($field, true),
            'entity' => var_export($entity, true),
            'label_property' => var_export($labelProperty, true),
            'field_label_snippet' => var_export($name . '.cms.fields.' . $field, true),
            'field_placeholder_snippet' => var_export($name . '.cms.placeholders.' . $field, true),
        ]);
    }

    private function administrationSnippet(string $name, string $field, string $humanName): string
    {
        return json_encode([
            $name => [
                'cms' => [
                    'label' => $humanName,
                    'fields' => [
                        $field => $humanName,
                    ],
                    'placeholders' => [
                        $field => 'Select ' . $humanName,
                    ],
                ],
            ],
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR) . "\n";
    }

    private function resolver(
        string $namespace,
        string $element,
        string $name,
        string $field,
        string $definition,
    ): string {
        $definitionClass = substr($definition, (int) strrpos($definition, '\\') + 1);

        return $this->renderer->render(<<<'PHP'
<?php declare(strict_types=1);

namespace %jetpack.namespace%;

use %jetpack.definition%;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayStruct;

final class %jetpack.element%CmsElementResolver extends AbstractCmsElementResolver
{
    private const CONFIG_KEY = %jetpack.field%;

    public function getType(): string
    {
        return %jetpack.name%;
    }

    public function collect(
        CmsSlotEntity $slot,
        ResolverContext $resolverContext,
    ): ?CriteriaCollection {
        $config = $slot->getFieldConfig()->get(self::CONFIG_KEY);
        if ($config === null || !$config->isStatic() || $config->getValue() === null) {
            return null;
        }

        $collection = new CriteriaCollection();
        $collection->add(
            $this->resultKey($slot),
            %jetpack.definition_class%::class,
            new Criteria([$config->getStringValue()]),
        );

        return $collection;
    }

    public function enrich(
        CmsSlotEntity $slot,
        ResolverContext $resolverContext,
        ElementDataCollection $result,
    ): void {
        $data = new ArrayStruct([self::CONFIG_KEY => null]);
        $slot->setData($data);

        $config = $slot->getFieldConfig()->get(self::CONFIG_KEY);
        if ($config === null || !$config->isStatic() || $config->getValue() === null) {
            return;
        }

        $entity = $result
            ->get($this->resultKey($slot))
            ?->getEntities()
            ->get($config->getStringValue());

        if ($entity !== null) {
            $data->set(self::CONFIG_KEY, $entity);
        }
    }

    private function resultKey(CmsSlotEntity $slot): string
    {
        return %jetpack.result_prefix% . $slot->getUniqueIdentifier();
    }
}

PHP, [
            'namespace' => $namespace,
            'definition' => $definition,
            'element' => $element,
            'field' => var_export($field, true),
            'name' => var_export($name, true),
            'definition_class' => $definitionClass,
            'result_prefix' => var_export($field . '_', true),
        ]);
    }

    private function storefrontTemplate(string $name, string $field, string $labelProperty): string
    {
        return $this->renderer->render(<<<'TWIG'
{% set cmsEntity = element.data.get('%jetpack.field%') %}

{% if cmsEntity %}
    <div class="cms-element-%jetpack.name%">
        {{ cmsEntity['%jetpack.label_property%'] }}
    </div>
{% endif %}

TWIG, [
            'field' => $field,
            'name' => $name,
            'label_property' => $labelProperty,
        ]);
    }
}
