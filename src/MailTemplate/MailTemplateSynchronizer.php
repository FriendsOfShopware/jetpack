<?php declare(strict_types=1);

namespace Frosh\Jetpack\MailTemplate;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;

/**
 * @internal
 *
 * @phpstan-import-type MailLanguage from MailTemplateStore
 * @phpstan-import-type MailTemplateDeclaration from MailTemplateManifest
 * @phpstan-import-type MailTemplateWrite from MailTemplateStore
 * @phpstan-import-type MailTranslation from MailTemplateManifest
 * @phpstan-import-type StoredTranslation from MailTemplateStore
 */
final class MailTemplateSynchronizer
{
    public function __construct(
        private readonly YamlMailTemplateLoader $loader,
        private readonly MailTemplatePayloadCompiler $compiler,
        private readonly MailTemplateHasher $hasher,
        private readonly MailTemplateStore $store,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sync(Bundle $bundle, Context $context): void
    {
        $manifest = $this->loader->load($bundle);
        $languages = $this->store->languages();
        $owned = $this->store->owned($bundle->getName());
        $writes = [];

        foreach ($manifest->templates as $template) {
            $reference = $owned[$template['technicalName']]
                ?? $this->compiler->reference($bundle->getName(), $template['technicalName']);
            $this->assertNoCollision($reference);
            $writes[] = $this->write($reference, $template, $manifest->defaultLocale, $languages);
            unset($owned[$template['technicalName']]);
        }

        $systemContext = $context->getScope() === Context::SYSTEM_SCOPE ? $context : Context::createDefaultContext();
        $this->store->apply($writes, array_values($owned), $systemContext);
    }

    public function remove(Bundle $bundle, Context $context): void
    {
        $systemContext = $context->getScope() === Context::SYSTEM_SCOPE ? $context : Context::createDefaultContext();
        $this->store->apply([], array_values($this->store->owned($bundle->getName())), $systemContext);
    }

    private function assertNoCollision(MailTemplateReference $reference): void
    {
        $typeId = $this->store->typeId($reference->technicalName);
        if ($typeId !== null && $typeId !== $reference->templateTypeId) {
            throw MailTemplateException::technicalNameOwnedElsewhere($reference->technicalName, $typeId);
        }

        $templateTypeId = $this->store->templateTypeId($reference->templateId);
        if ($templateTypeId !== null && $templateTypeId !== $reference->templateTypeId) {
            throw MailTemplateException::templateIdCollision($reference->templateId, $templateTypeId);
        }
    }

    /**
     * @param MailTemplateDeclaration $template
     * @param list<MailLanguage> $languages
     *
     * @return MailTemplateWrite
     */
    private function write(
        MailTemplateReference $reference,
        array $template,
        string $defaultLocale,
        array $languages,
    ): array {
        $state = $this->store->state($reference);
        $selectedTranslations = $this->selectTranslations($template, $defaultLocale, $languages);
        $translations = [];

        foreach ($selectedTranslations as $languageId => $translation) {
            $translationState = $state['translations'][$languageId] ?? $this->emptyTranslationState();
            $compiled = $this->compiler->translation(
                $languageId,
                $translation,
                $translationState,
                $template['updatePolicy'] === 'overwrite',
            );
            if ($compiled['preservedName'] || $compiled['preservedContent']) {
                $this->logger->warning('Preserved a merchant-modified Jetpack mail template translation.', [
                    'bundle' => $reference->bundleName,
                    'technicalName' => $reference->technicalName,
                    'languageId' => $languageId,
                    'preservedName' => $compiled['preservedName'],
                    'preservedContent' => $compiled['preservedContent'],
                ]);
            }
            $translations[] = $compiled['write'];
        }

        $removeLanguageIds = [];
        foreach ($state['translations'] as $languageId => $translationState) {
            if ($translationState['managed'] && !isset($selectedTranslations[$languageId])) {
                $removeLanguageIds[] = $languageId;
            }
        }

        return [
            'reference' => $reference,
            'availableEntities' => $template['availableEntities'],
            'availableEntitiesHash' => $this->hasher->availableEntities($template['availableEntities']),
            'translations' => $translations,
            'removeLanguageIds' => $removeLanguageIds,
        ];
    }

    /**
     * @param MailTemplateDeclaration $template
     * @param list<MailLanguage> $languages
     *
     * @return array<string, MailTranslation>
     */
    private function selectTranslations(array $template, string $defaultLocale, array $languages): array
    {
        $selected = [];
        foreach ($languages as $language) {
            $translation = $template['translations'][$language['locale']] ?? null;
            if ($translation === null && $language['system']) {
                $translation = $template['translations'][$defaultLocale];
            }
            if ($translation !== null) {
                $selected[$language['id']] = $translation;
            }
        }

        return $selected;
    }

    /**
     * @return StoredTranslation
     */
    private function emptyTranslationState(): array
    {
        return [
            'currentNameHash' => null,
            'currentContentHash' => null,
            'synchronizedNameHash' => null,
            'synchronizedContentHash' => null,
            'managed' => false,
        ];
    }
}
