<?php declare(strict_types=1);

namespace Frosh\Jetpack\CustomField;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;

/**
 * @internal
 */
final class CustomFieldSynchronizer
{
    public function __construct(
        private readonly YamlCustomFieldLoader $loader,
        private readonly CustomFieldPayloadCompiler $compiler,
        private readonly CustomFieldSetStore $store,
    ) {
    }

    public function sync(Bundle $bundle, Context $context): void
    {
        $definition = $this->loader->load($bundle);
        $systemContext = $context->getScope() === Context::SYSTEM_SCOPE ? $context : Context::createDefaultContext();
        $existingSets = $this->store->owned($bundle->getName());
        $ownership = [];
        $payloads = [];
        $obsoleteRelations = [];
        $obsoleteFields = [];

        foreach ($definition->sets as $set) {
            $existingSetId = $existingSets[$set['name']] ?? null;
            if ($existingSetId === null) {
                $deterministicId = $this->compiler->setId($bundle->getName(), $set['name']);
                if ($this->store->setExists($deterministicId)) {
                    $existingSetId = $deterministicId;
                }
            }
            $children = $existingSetId === null
                ? ['relations' => [], 'fields' => []]
                : $this->store->children($existingSetId);
            $compiled = $this->compiler->compile(
                $bundle->getName(),
                $set,
                $existingSetId,
                $children['relations'],
                $children['fields'],
                $this->store->supportsIncludeInSearch(),
            );

            $ownership[$set['name']] = $compiled['setId'];
            $payloads[] = $compiled['payload'];
            $obsoleteRelations = array_merge($obsoleteRelations, $compiled['obsoleteRelations']);
            $obsoleteFields = array_merge($obsoleteFields, $compiled['obsoleteFields']);
            unset($existingSets[$set['name']]);
        }

        $this->store->deleteRelations($obsoleteRelations, $systemContext);
        $this->store->deleteFields($obsoleteFields, $systemContext);
        $this->store->deleteSets(array_values($existingSets), $systemContext);
        $this->store->upsert($payloads, $systemContext);
        $this->store->replaceOwnership($bundle->getName(), $ownership);
    }

    public function remove(Bundle $bundle, Context $context): void
    {
        $systemContext = $context->getScope() === Context::SYSTEM_SCOPE ? $context : Context::createDefaultContext();
        $this->store->deleteSets(array_values($this->store->owned($bundle->getName())), $systemContext);
        $this->store->replaceOwnership($bundle->getName(), []);
    }
}
