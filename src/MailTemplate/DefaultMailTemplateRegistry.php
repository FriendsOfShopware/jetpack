<?php declare(strict_types=1);

namespace Frosh\Jetpack\MailTemplate;

use Frosh\Jetpack\Entity\BundleResolver;

/**
 * @internal
 */
final class DefaultMailTemplateRegistry implements MailTemplateRegistry
{
    public function __construct(
        private readonly BundleResolver $bundleResolver,
        private readonly MailTemplateStore $store,
    ) {
    }

    public function get(string $bundle, string $technicalName): MailTemplateReference
    {
        $bundleName = $this->bundleResolver->resolve($bundle)->getName();
        $reference = $this->store->reference($bundleName, $technicalName);
        if (!$reference instanceof MailTemplateReference) {
            throw MailTemplateException::notFound($bundleName, $technicalName);
        }

        return $reference;
    }
}
