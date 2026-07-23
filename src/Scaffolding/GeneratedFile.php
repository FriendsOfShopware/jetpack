<?php declare(strict_types=1);

namespace Frosh\Jetpack\Scaffolding;

use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
final readonly class GeneratedFile
{
    public string $relativePath;

    public function __construct(string $relativePath, public string $content)
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        if (
            $relativePath === ''
            || Path::isAbsolute($relativePath)
            || str_ends_with($relativePath, '/')
            || \in_array('..', explode('/', $relativePath), true)
        ) {
            throw new \InvalidArgumentException('Generated file path must stay below the scaffold root.');
        }
        if ($content === '') {
            throw new \InvalidArgumentException('Generated file content must not be empty.');
        }

        $this->relativePath = $relativePath;
    }
}
