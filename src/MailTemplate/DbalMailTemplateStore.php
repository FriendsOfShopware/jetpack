<?php declare(strict_types=1);

namespace Frosh\Jetpack\MailTemplate;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation\MailTemplateTypeTranslationCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-import-type MailLanguage from MailTemplateStore
 * @phpstan-import-type MailTemplateWrite from MailTemplateStore
 * @phpstan-import-type StoredMailTemplate from MailTemplateStore
 * @phpstan-import-type StoredTranslation from MailTemplateStore
 */
final class DbalMailTemplateStore implements MailTemplateStore
{
    /**
     * @param EntityRepository<MailTemplateTypeCollection> $typeRepository
     * @param EntityRepository<MailTemplateCollection> $templateRepository
     * @param EntityRepository<MailTemplateTypeTranslationCollection> $typeTranslationRepository
     * @param EntityRepository<MailTemplateTranslationCollection> $templateTranslationRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $typeRepository,
        private readonly EntityRepository $templateRepository,
        private readonly EntityRepository $typeTranslationRepository,
        private readonly EntityRepository $templateTranslationRepository,
        private readonly MailTemplateHasher $hasher,
    ) {
    }

    public function languages(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT LOWER(HEX(`language`.`id`)) AS `id`, `locale`.`code` AS `locale`,
                       (`language`.`id` = :systemLanguageId) AS `is_system`
                FROM `language`
                INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
                ORDER BY `language`.`created_at`, `language`.`id`
                SQL,
            ['systemLanguageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)],
        );
        $languages = [];
        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            $locale = $row['locale'] ?? null;
            if (!\is_string($id) || !\is_string($locale)) {
                throw new \RuntimeException('The Shopware language table contains an invalid row.');
            }
            $languages[] = [
                'id' => $id,
                'locale' => $locale,
                'system' => (bool) ($row['is_system'] ?? false),
            ];
        }

        return $languages;
    }

    public function owned(string $bundleName): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT `technical_name`, LOWER(HEX(`mail_template_type_id`)) AS `type_id`,
                       LOWER(HEX(`mail_template_id`)) AS `template_id`
                FROM `frosh_jetpack_mail_template`
                WHERE `bundle_name` = :bundle
                ORDER BY `technical_name`
                SQL,
            ['bundle' => $bundleName],
        );
        $owned = [];
        foreach ($rows as $row) {
            $technicalName = $row['technical_name'] ?? null;
            $typeId = $row['type_id'] ?? null;
            $templateId = $row['template_id'] ?? null;
            if (!\is_string($technicalName) || !\is_string($typeId) || !\is_string($templateId)) {
                throw new \RuntimeException('The Jetpack mail template ownership table contains an invalid row.');
            }
            $owned[$technicalName] = new MailTemplateReference($bundleName, $technicalName, $typeId, $templateId);
        }

        return $owned;
    }

    public function reference(string $bundleName, string $technicalName): ?MailTemplateReference
    {
        return $this->owned($bundleName)[$technicalName] ?? null;
    }

    public function typeId(string $technicalName): ?string
    {
        $id = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => $technicalName],
        );

        return \is_string($id) ? $id : null;
    }

    public function templateTypeId(string $templateId): ?string
    {
        $typeId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`mail_template_type_id`)) FROM `mail_template` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($templateId)],
        );

        return \is_string($typeId) ? $typeId : null;
    }

    public function state(MailTemplateReference $reference): array
    {
        $typeExists = $this->connection->fetchOne(
            'SELECT 1 FROM `mail_template_type` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($reference->templateTypeId)],
        ) !== false;
        $templateExists = $this->connection->fetchOne(
            'SELECT 1 FROM `mail_template` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($reference->templateId)],
        ) !== false;
        $translations = [];

        $nameRows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT LOWER(HEX(`language_id`)) AS `language_id`, `name`
                FROM `mail_template_type_translation`
                WHERE `mail_template_type_id` = :typeId
                SQL,
            ['typeId' => Uuid::fromHexToBytes($reference->templateTypeId)],
        );
        foreach ($nameRows as $row) {
            $languageId = $row['language_id'] ?? null;
            $name = $row['name'] ?? null;
            if (!\is_string($languageId) || !\is_string($name)) {
                throw new \RuntimeException('A Shopware mail template type translation contains an invalid row.');
            }
            $translations[$languageId] = $this->translationState();
            $translations[$languageId]['currentNameHash'] = $this->hasher->name($name);
        }

        $contentRows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT LOWER(HEX(`language_id`)) AS `language_id`, `subject`, `sender_name`, `description`,
                       `content_html`, `content_plain`
                FROM `mail_template_translation`
                WHERE `mail_template_id` = :templateId
                SQL,
            ['templateId' => Uuid::fromHexToBytes($reference->templateId)],
        );
        foreach ($contentRows as $row) {
            $languageId = $row['language_id'] ?? null;
            if (!\is_string($languageId)) {
                throw new \RuntimeException('A Shopware mail template translation contains an invalid language ID.');
            }
            $translations[$languageId] ??= $this->translationState();
            $translations[$languageId]['currentContentHash'] = $this->hasher->content(
                \is_string($row['subject'] ?? null) ? $row['subject'] : '',
                \is_string($row['sender_name'] ?? null) ? $row['sender_name'] : null,
                \is_string($row['description'] ?? null) ? $row['description'] : null,
                \is_string($row['content_html'] ?? null) ? $row['content_html'] : '',
                \is_string($row['content_plain'] ?? null) ? $row['content_plain'] : '',
            );
        }

        $managedRows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT LOWER(HEX(`translation`.`language_id`)) AS `language_id`,
                       `translation`.`type_name_hash`, `translation`.`content_hash`
                FROM `frosh_jetpack_mail_template_translation` `translation`
                INNER JOIN `frosh_jetpack_mail_template` `template`
                    ON `template`.`id` = `translation`.`mail_template_ownership_id`
                WHERE `template`.`bundle_name` = :bundle AND `template`.`technical_name` = :technicalName
                SQL,
            ['bundle' => $reference->bundleName, 'technicalName' => $reference->technicalName],
        );
        foreach ($managedRows as $row) {
            $languageId = $row['language_id'] ?? null;
            $nameHash = $row['type_name_hash'] ?? null;
            $contentHash = $row['content_hash'] ?? null;
            if (!\is_string($languageId) || !\is_string($nameHash) || !\is_string($contentHash)) {
                throw new \RuntimeException('The Jetpack mail template translation ownership table contains an invalid row.');
            }
            $translations[$languageId] ??= $this->translationState();
            $translations[$languageId]['synchronizedNameHash'] = $nameHash;
            $translations[$languageId]['synchronizedContentHash'] = $contentHash;
            $translations[$languageId]['managed'] = true;
        }

        return [
            'typeExists' => $typeExists,
            'templateExists' => $templateExists,
            'translations' => $translations,
        ];
    }

    public function apply(array $writes, array $removals, Context $context): void
    {
        $this->connection->transactional(function () use ($writes, $removals, $context): void {
            foreach ($writes as $write) {
                $this->write($write, $context);
            }
            foreach ($removals as $reference) {
                $this->remove($reference, $context);
            }
        });
    }

    /**
     * @param MailTemplateWrite $write
     */
    private function write(array $write, Context $context): void
    {
        $reference = $write['reference'];
        $this->typeRepository->upsert([[
            'id' => $reference->templateTypeId,
            'technicalName' => $reference->technicalName,
            'availableEntities' => $write['availableEntities'],
        ]], $context);
        $this->templateRepository->upsert([[
            'id' => $reference->templateId,
            'mailTemplateTypeId' => $reference->templateTypeId,
            'systemDefault' => true,
        ]], $context);

        $typeTranslations = [];
        $templateTranslations = [];
        foreach ($write['translations'] as $translation) {
            if ($translation['name'] !== null) {
                $typeTranslations[] = [
                    'mailTemplateTypeId' => $reference->templateTypeId,
                    'languageId' => $translation['languageId'],
                    'name' => $translation['name'],
                ];
            }
            if ($translation['subject'] !== null) {
                $templateTranslations[] = [
                    'mailTemplateId' => $reference->templateId,
                    'languageId' => $translation['languageId'],
                    'subject' => $translation['subject'],
                    'senderName' => $translation['senderName'],
                    'description' => $translation['description'],
                    'contentHtml' => $translation['contentHtml'],
                    'contentPlain' => $translation['contentPlain'],
                ];
            }
        }
        if ($typeTranslations !== []) {
            $this->typeTranslationRepository->upsert($typeTranslations, $context);
        }
        if ($templateTranslations !== []) {
            $this->templateTranslationRepository->upsert($templateTranslations, $context);
        }

        $ownershipId = $this->ownershipId($reference);
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO `frosh_jetpack_mail_template`
                    (`id`, `bundle_name`, `technical_name`, `mail_template_type_id`, `mail_template_id`, `available_entities_hash`)
                VALUES (:id, :bundle, :technicalName, :typeId, :templateId, :availableEntitiesHash)
                ON DUPLICATE KEY UPDATE
                    `mail_template_type_id` = VALUES(`mail_template_type_id`),
                    `mail_template_id` = VALUES(`mail_template_id`),
                    `available_entities_hash` = VALUES(`available_entities_hash`)
                SQL,
            [
                'id' => Uuid::fromHexToBytes($ownershipId),
                'bundle' => $reference->bundleName,
                'technicalName' => $reference->technicalName,
                'typeId' => Uuid::fromHexToBytes($reference->templateTypeId),
                'templateId' => Uuid::fromHexToBytes($reference->templateId),
                'availableEntitiesHash' => $write['availableEntitiesHash'],
            ],
        );

        foreach ($write['translations'] as $translation) {
            $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO `frosh_jetpack_mail_template_translation`
                        (`mail_template_ownership_id`, `language_id`, `type_name_hash`, `content_hash`)
                    VALUES (:ownershipId, :languageId, :nameHash, :contentHash)
                    ON DUPLICATE KEY UPDATE
                        `type_name_hash` = VALUES(`type_name_hash`),
                        `content_hash` = VALUES(`content_hash`)
                    SQL,
                [
                    'ownershipId' => Uuid::fromHexToBytes($ownershipId),
                    'languageId' => Uuid::fromHexToBytes($translation['languageId']),
                    'nameHash' => $translation['synchronizedNameHash'],
                    'contentHash' => $translation['synchronizedContentHash'],
                ],
            );
        }

        foreach ($write['removeLanguageIds'] as $languageId) {
            $this->typeTranslationRepository->delete([[
                'mailTemplateTypeId' => $reference->templateTypeId,
                'languageId' => $languageId,
            ]], $context);
            $this->templateTranslationRepository->delete([[
                'mailTemplateId' => $reference->templateId,
                'languageId' => $languageId,
            ]], $context);
            $this->connection->delete('frosh_jetpack_mail_template_translation', [
                'mail_template_ownership_id' => Uuid::fromHexToBytes($ownershipId),
                'language_id' => Uuid::fromHexToBytes($languageId),
            ]);
        }
    }

    private function remove(MailTemplateReference $reference, Context $context): void
    {
        if ($this->templateTypeId($reference->templateId) !== null) {
            $this->templateRepository->delete([['id' => $reference->templateId]], $context);
        }
        $remainingTemplates = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mail_template` WHERE `mail_template_type_id` = :typeId',
            ['typeId' => Uuid::fromHexToBytes($reference->templateTypeId)],
        );
        if ($remainingTemplates === 0 && $this->connection->fetchOne(
            'SELECT 1 FROM `mail_template_type` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($reference->templateTypeId)],
        ) !== false) {
            $this->typeRepository->delete([['id' => $reference->templateTypeId]], $context);
        }
        $this->connection->delete('frosh_jetpack_mail_template', [
            'bundle_name' => $reference->bundleName,
            'technical_name' => $reference->technicalName,
        ]);
    }

    /**
     * @return StoredTranslation
     */
    private function translationState(): array
    {
        return [
            'currentNameHash' => null,
            'currentContentHash' => null,
            'synchronizedNameHash' => null,
            'synchronizedContentHash' => null,
            'managed' => false,
        ];
    }

    private function ownershipId(MailTemplateReference $reference): string
    {
        return Uuid::fromStringToHex(\sprintf(
            'frosh.jetpack.mail-template.ownership.%s.%s',
            $reference->bundleName,
            $reference->technicalName,
        ));
    }
}
