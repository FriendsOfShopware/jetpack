<?php declare(strict_types=1);

namespace Frosh\Jetpack\MailTemplate;

final class MailTemplateException extends \RuntimeException
{
    public static function invalidDefinition(string $path, string $reason): self
    {
        return new self(\sprintf('Invalid Jetpack mail template definition "%s": %s', $path, $reason));
    }

    public static function technicalNameOwnedElsewhere(string $technicalName, string $typeId): self
    {
        return new self(\sprintf(
            'Mail template type "%s" already exists with unowned ID "%s". Use a bundle-specific technical name; Jetpack never adopts existing mail templates.',
            $technicalName,
            $typeId,
        ));
    }

    public static function templateIdCollision(string $templateId, string $typeId): self
    {
        return new self(\sprintf(
            'Deterministic Jetpack mail template ID "%s" already belongs to mail template type "%s".',
            $templateId,
            $typeId,
        ));
    }

    public static function notFound(string $bundle, string $technicalName): self
    {
        return new self(\sprintf(
            'Jetpack mail template "%s" is not owned by bundle "%s".',
            $technicalName,
            $bundle,
        ));
    }
}
