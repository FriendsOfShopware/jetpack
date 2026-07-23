<?php declare(strict_types=1);

namespace Frosh\Jetpack\MailTemplate;

use Shopware\Core\Framework\Context;

/**
 * @internal
 *
 * @phpstan-type MailLanguage array{id: string, locale: string, system: bool}
 * @phpstan-type StoredTranslation array{
 *     currentNameHash: string|null,
 *     currentContentHash: string|null,
 *     synchronizedNameHash: string|null,
 *     synchronizedContentHash: string|null,
 *     managed: bool
 * }
 * @phpstan-type StoredMailTemplate array{
 *     typeExists: bool,
 *     templateExists: bool,
 *     translations: array<string, StoredTranslation>
 * }
 * @phpstan-type MailTranslationWrite array{
 *     languageId: string,
 *     name: string|null,
 *     subject: string|null,
 *     senderName: string|null,
 *     description: string|null,
 *     contentHtml: string|null,
 *     contentPlain: string|null,
 *     synchronizedNameHash: string,
 *     synchronizedContentHash: string
 * }
 * @phpstan-type MailTemplateWrite array{
 *     reference: MailTemplateReference,
 *     availableEntities: array<string, string|null>,
 *     availableEntitiesHash: string,
 *     translations: list<MailTranslationWrite>,
 *     removeLanguageIds: list<string>
 * }
 */
interface MailTemplateStore
{
    /**
     * @return list<MailLanguage>
     */
    public function languages(): array;

    /**
     * @return array<string, MailTemplateReference>
     */
    public function owned(string $bundleName): array;

    public function reference(string $bundleName, string $technicalName): ?MailTemplateReference;

    public function typeId(string $technicalName): ?string;

    public function templateTypeId(string $templateId): ?string;

    /**
     * @return StoredMailTemplate
     */
    public function state(MailTemplateReference $reference): array;

    /**
     * @param list<MailTemplateWrite> $writes
     * @param list<MailTemplateReference> $removals
     */
    public function apply(array $writes, array $removals, Context $context): void;
}
