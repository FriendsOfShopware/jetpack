<?php declare(strict_types=1);

namespace Frosh\Jetpack\MailTemplate;

interface MailTemplateRegistry
{
    /**
     * @param class-string|string $bundle
     */
    public function get(string $bundle, string $technicalName): MailTemplateReference;
}
