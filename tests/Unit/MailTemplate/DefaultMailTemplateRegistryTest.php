<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\MailTemplate;

use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\MailTemplate\DefaultMailTemplateRegistry;
use Frosh\Jetpack\MailTemplate\MailTemplateException;
use Frosh\Jetpack\MailTemplate\MailTemplateHasher;
use Frosh\Jetpack\MailTemplate\MailTemplatePayloadCompiler;
use Frosh\Jetpack\Tests\Fixture\InMemoryMailTemplateStore;
use Frosh\Jetpack\Tests\Fixture\MailTemplateTestBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(DefaultMailTemplateRegistry::class)]
final class DefaultMailTemplateRegistryTest extends TestCase
{
    public function testResolvesReferenceByBundleClass(): void
    {
        $bundle = new MailTemplateTestBundle();
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundles')->willReturn([$bundle]);
        $store = new InMemoryMailTemplateStore();
        $reference = (new MailTemplatePayloadCompiler(new MailTemplateHasher()))->reference(
            $bundle->getName(),
            'acme_order_confirmation',
        );
        $store->ownership[$bundle->getName()][$reference->technicalName] = $reference;
        $registry = new DefaultMailTemplateRegistry(new BundleResolver($kernel), $store);

        static::assertSame($reference, $registry->get(MailTemplateTestBundle::class, 'acme_order_confirmation'));
    }

    public function testRejectsUnknownReference(): void
    {
        $bundle = new MailTemplateTestBundle();
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundle')->willReturn($bundle);
        $registry = new DefaultMailTemplateRegistry(new BundleResolver($kernel), new InMemoryMailTemplateStore());

        $this->expectExceptionObject(MailTemplateException::notFound($bundle->getName(), 'acme_missing'));

        $registry->get($bundle->getName(), 'acme_missing');
    }
}
