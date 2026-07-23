<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\MailTemplate;

use Frosh\Jetpack\MailTemplate\MailTemplateHasher;
use Frosh\Jetpack\MailTemplate\MailTemplatePayloadCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MailTemplatePayloadCompiler::class)]
#[CoversClass(MailTemplateHasher::class)]
final class MailTemplatePayloadCompilerTest extends TestCase
{
    public function testBuildsStableBundleScopedReferences(): void
    {
        $compiler = new MailTemplatePayloadCompiler(new MailTemplateHasher());

        $first = $compiler->reference('AcmePlugin', 'acme_order_confirmation');
        $second = $compiler->reference('AcmePlugin', 'acme_order_confirmation');
        $otherBundle = $compiler->reference('OtherPlugin', 'acme_order_confirmation');

        static::assertSame($first->templateTypeId, $second->templateTypeId);
        static::assertSame($first->templateId, $second->templateId);
        static::assertNotSame($first->templateTypeId, $otherBundle->templateTypeId);
    }

    public function testPreservesMerchantChangesByDefault(): void
    {
        $hasher = new MailTemplateHasher();
        $compiler = new MailTemplatePayloadCompiler($hasher);
        $translation = $this->translation();
        $previousContentHash = $hasher->content('Previous', null, null, 'Previous HTML', 'Previous plain');

        $compiled = $compiler->translation('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $translation, [
            'currentNameHash' => $hasher->name($translation['name']),
            'currentContentHash' => hash('sha256', 'merchant content'),
            'synchronizedNameHash' => $hasher->name($translation['name']),
            'synchronizedContentHash' => $previousContentHash,
            'managed' => true,
        ], false);

        static::assertFalse($compiled['preservedName']);
        static::assertTrue($compiled['preservedContent']);
        static::assertSame($translation['name'], $compiled['write']['name']);
        static::assertNull($compiled['write']['subject']);
        static::assertSame($previousContentHash, $compiled['write']['synchronizedContentHash']);
    }

    public function testOverwritePolicyReplacesMerchantChanges(): void
    {
        $hasher = new MailTemplateHasher();
        $compiler = new MailTemplatePayloadCompiler($hasher);
        $translation = $this->translation();

        $compiled = $compiler->translation('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $translation, [
            'currentNameHash' => hash('sha256', 'merchant name'),
            'currentContentHash' => hash('sha256', 'merchant content'),
            'synchronizedNameHash' => hash('sha256', 'previous name'),
            'synchronizedContentHash' => hash('sha256', 'previous content'),
            'managed' => true,
        ], true);

        static::assertFalse($compiled['preservedName']);
        static::assertFalse($compiled['preservedContent']);
        static::assertSame($translation['subject'], $compiled['write']['subject']);
        static::assertSame($translation['contentHtml'], $compiled['write']['contentHtml']);
    }

    /**
     * @return array{
     *     locale: string,
     *     name: string,
     *     subject: string,
     *     senderName: string|null,
     *     description: string|null,
     *     contentHtml: string,
     *     contentPlain: string
     * }
     */
    private function translation(): array
    {
        return [
            'locale' => 'en-GB',
            'name' => 'Order confirmation',
            'subject' => 'Your order',
            'senderName' => 'Acme',
            'description' => 'Sent after order placement',
            'contentHtml' => '<h1>Your order</h1>',
            'contentPlain' => 'Your order',
        ];
    }
}
