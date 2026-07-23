<?php declare(strict_types=1);

namespace Frosh\Jetpack\MailTemplate;

use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-import-type MailTranslation from MailTemplateManifest
 * @phpstan-import-type MailTranslationWrite from MailTemplateStore
 * @phpstan-import-type StoredTranslation from MailTemplateStore
 */
final class MailTemplatePayloadCompiler
{
    public function __construct(private readonly MailTemplateHasher $hasher)
    {
    }

    public function reference(string $bundleName, string $technicalName): MailTemplateReference
    {
        return new MailTemplateReference(
            $bundleName,
            $technicalName,
            $this->id($bundleName, 'type', $technicalName),
            $this->id($bundleName, 'template', $technicalName),
        );
    }

    /**
     * @param MailTranslation $translation
     * @param StoredTranslation $state
     *
     * @return array{write: MailTranslationWrite, preservedName: bool, preservedContent: bool}
     */
    public function translation(
        string $languageId,
        array $translation,
        array $state,
        bool $overwrite,
    ): array {
        $nameHash = $this->hasher->name($translation['name']);
        $contentHash = $this->hasher->content(
            $translation['subject'],
            $translation['senderName'],
            $translation['description'],
            $translation['contentHtml'],
            $translation['contentPlain'],
        );
        $writeName = $this->canWrite(
            $state['currentNameHash'],
            $state['synchronizedNameHash'],
            $nameHash,
            $overwrite,
        );
        $writeContent = $this->canWrite(
            $state['currentContentHash'],
            $state['synchronizedContentHash'],
            $contentHash,
            $overwrite,
        );

        return [
            'write' => [
                'languageId' => $languageId,
                'name' => $writeName ? $translation['name'] : null,
                'subject' => $writeContent ? $translation['subject'] : null,
                'senderName' => $writeContent ? $translation['senderName'] : null,
                'description' => $writeContent ? $translation['description'] : null,
                'contentHtml' => $writeContent ? $translation['contentHtml'] : null,
                'contentPlain' => $writeContent ? $translation['contentPlain'] : null,
                'synchronizedNameHash' => $writeName
                    ? $nameHash
                    : ($state['synchronizedNameHash'] ?? $state['currentNameHash'] ?? $nameHash),
                'synchronizedContentHash' => $writeContent
                    ? $contentHash
                    : ($state['synchronizedContentHash'] ?? $state['currentContentHash'] ?? $contentHash),
            ],
            'preservedName' => !$writeName,
            'preservedContent' => !$writeContent,
        ];
    }

    private function canWrite(?string $currentHash, ?string $synchronizedHash, string $declaredHash, bool $overwrite): bool
    {
        return $overwrite
            || $currentHash === null
            || $currentHash === $synchronizedHash
            || $currentHash === $declaredHash;
    }

    private function id(string ...$parts): string
    {
        return Uuid::fromStringToHex('frosh.jetpack.mail-template.' . implode('.', $parts));
    }
}
