<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture;

use Frosh\Jetpack\MailTemplate\MailTemplateReference;
use Frosh\Jetpack\MailTemplate\MailTemplateStore;
use Shopware\Core\Framework\Context;

/**
 * @phpstan-import-type MailTemplateWrite from MailTemplateStore
 */
final class InMemoryMailTemplateStore implements MailTemplateStore
{
    /**
     * @var list<array{id: string, locale: string, system: bool}>
     */
    public array $languageRows = [];

    /**
     * @var array<string, array<string, MailTemplateReference>>
     */
    public array $ownership = [];

    /**
     * @var array<string, string>
     */
    public array $typeIds = [];

    /**
     * @var array<string, string>
     */
    public array $templateTypeIds = [];

    /**
     * @var array<string, array{
     *     typeExists: bool,
     *     templateExists: bool,
     *     translations: array<string, array{
     *         currentNameHash: string|null,
     *         currentContentHash: string|null,
     *         synchronizedNameHash: string|null,
     *         synchronizedContentHash: string|null,
     *         managed: bool
     *     }>
     * }>
     */
    public array $states = [];

    /**
     * @var list<MailTemplateWrite>
     */
    public array $writes = [];

    /**
     * @var list<MailTemplateReference>
     */
    public array $removals = [];

    public function languages(): array
    {
        return $this->languageRows;
    }

    public function owned(string $bundleName): array
    {
        return $this->ownership[$bundleName] ?? [];
    }

    public function reference(string $bundleName, string $technicalName): ?MailTemplateReference
    {
        return $this->ownership[$bundleName][$technicalName] ?? null;
    }

    public function typeId(string $technicalName): ?string
    {
        return $this->typeIds[$technicalName] ?? null;
    }

    public function templateTypeId(string $templateId): ?string
    {
        return $this->templateTypeIds[$templateId] ?? null;
    }

    public function state(MailTemplateReference $reference): array
    {
        return $this->states[$reference->technicalName] ?? [
            'typeExists' => false,
            'templateExists' => false,
            'translations' => [],
        ];
    }

    public function apply(array $writes, array $removals, Context $context): void
    {
        $this->writes = array_merge($this->writes, $writes);
        $this->removals = array_merge($this->removals, $removals);
        foreach ($writes as $write) {
            $reference = $write['reference'];
            $this->ownership[$reference->bundleName][$reference->technicalName] = $reference;
            $this->typeIds[$reference->technicalName] = $reference->templateTypeId;
            $this->templateTypeIds[$reference->templateId] = $reference->templateTypeId;
        }
        foreach ($removals as $reference) {
            unset($this->ownership[$reference->bundleName][$reference->technicalName]);
        }
    }
}
