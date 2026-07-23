<?php declare(strict_types=1);

namespace Frosh\Jetpack\MailTemplate;

/**
 * @internal
 */
final class MailTemplateHasher
{
    /**
     * @param array<string, string|null> $availableEntities
     */
    public function availableEntities(array $availableEntities): string
    {
        return $this->hash($availableEntities);
    }

    public function name(string $name): string
    {
        return hash('sha256', $name);
    }

    public function content(
        string $subject,
        ?string $senderName,
        ?string $description,
        string $contentHtml,
        string $contentPlain,
    ): string {
        return $this->hash([
            'contentHtml' => $contentHtml,
            'contentPlain' => $contentPlain,
            'description' => $description,
            'senderName' => $senderName,
            'subject' => $subject,
        ]);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function hash(array $value): string
    {
        ksort($value);

        return hash('sha256', json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
    }
}
