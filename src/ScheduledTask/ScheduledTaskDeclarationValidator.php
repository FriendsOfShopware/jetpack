<?php declare(strict_types=1);

namespace Frosh\Jetpack\ScheduledTask;

use Frosh\Jetpack\Attribute\AsScheduledTask;
use Shopware\Core\Framework\Context;

/**
 * @internal
 */
final class ScheduledTaskDeclarationValidator
{
    private const NAME_PATTERN = '/^[a-z][a-z0-9_-]*(?:\.[a-z][a-z0-9_-]*)+$/';

    /**
     * @param \ReflectionClass<object> $reflection
     */
    public function validate(\ReflectionClass $reflection, AsScheduledTask $attribute, string $name): void
    {
        $class = $reflection->getName();

        if (!$reflection->isInstantiable()) {
            throw ScheduledTaskException::invalid($class, 'class must be concrete.');
        }
        if ($attribute->interval < 1) {
            throw ScheduledTaskException::invalid($class, 'interval must be at least 1 second.');
        }
        if (mb_strlen($name) > 255 || preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw ScheduledTaskException::invalid(
                $class,
                'name must contain at least two lower-case dot-separated segments and be at most 255 characters.',
            );
        }

        $method = $reflection->hasMethod('__invoke') ? $reflection->getMethod('__invoke') : null;
        if (!$method instanceof \ReflectionMethod || !$method->isPublic()) {
            throw ScheduledTaskException::invalid(
                $class,
                'must declare public function __invoke(Context $context): void.',
            );
        }

        $parameters = $method->getParameters();
        if (\count($parameters) !== 1) {
            throw ScheduledTaskException::invalid(
                $class,
                'must declare public function __invoke(Context $context): void.',
            );
        }

        $contextType = $parameters[0]->getType() ?? null;
        $returnType = $method->getReturnType();
        if (
            !$contextType instanceof \ReflectionNamedType
            || $contextType->isBuiltin()
            || $contextType->getName() !== Context::class
            || $contextType->allowsNull()
            || !$returnType instanceof \ReflectionNamedType
            || $returnType->getName() !== 'void'
        ) {
            throw ScheduledTaskException::invalid(
                $class,
                'must declare public function __invoke(Context $context): void.',
            );
        }

        if ($reflection->getFileName() === false) {
            throw ScheduledTaskException::invalid($class, 'source file could not be resolved.');
        }
    }
}
