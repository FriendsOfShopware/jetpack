<?php declare(strict_types=1);

namespace Frosh\Jetpack\MailTemplate;

/**
 * @codeCoverageIgnore
 */
final readonly class MailTemplateReference
{
    public function __construct(
        public string $bundleName,
        public string $technicalName,
        public string $templateTypeId,
        public string $templateId,
    ) {
    }
}
