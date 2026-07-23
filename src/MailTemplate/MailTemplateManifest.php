<?php declare(strict_types=1);

namespace Frosh\Jetpack\MailTemplate;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @phpstan-type MailTranslation array{
 *     locale: string,
 *     name: string,
 *     subject: string,
 *     senderName: string|null,
 *     description: string|null,
 *     contentHtml: string,
 *     contentPlain: string
 * }
 * @phpstan-type MailTemplateDeclaration array{
 *     technicalName: string,
 *     availableEntities: array<string, string|null>,
 *     updatePolicy: 'preserve-user-changes'|'overwrite',
 *     translations: array<string, MailTranslation>
 * }
 */
final readonly class MailTemplateManifest
{
    /**
     * @param list<MailTemplateDeclaration> $templates
     */
    public function __construct(
        public string $path,
        public string $defaultLocale,
        public array $templates,
    ) {
    }
}
