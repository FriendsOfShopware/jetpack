<?php declare(strict_types=1);

namespace Frosh\Jetpack\Command;

use Frosh\Jetpack\Entity\BundleResolver;
use Shopware\Core\Framework\Bundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
abstract class AbstractEntityCommand extends Command
{
    public function __construct(protected readonly BundleResolver $bundleResolver)
    {
        parent::__construct();
    }

    protected function bundle(InputInterface $input): Bundle
    {
        $name = $input->getArgument('bundle');
        if (!\is_string($name) || $name === '') {
            throw new \InvalidArgumentException('The bundle argument must be a non-empty string.');
        }

        return $this->bundleResolver->resolve($name);
    }
}
