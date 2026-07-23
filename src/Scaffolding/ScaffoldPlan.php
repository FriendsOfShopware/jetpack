<?php declare(strict_types=1);

namespace Frosh\Jetpack\Scaffolding;

/**
 * @internal
 */
final readonly class ScaffoldPlan
{
    public string $root;

    /**
     * @var list<GeneratedFile>
     */
    public array $files;

    /**
     * @var list<string>
     */
    public array $notes;

    /**
     * @param list<GeneratedFile> $files
     * @param list<string> $notes
     */
    public function __construct(string $root, array $files, public string $summary, array $notes = [])
    {
        $root = rtrim($root, '/\\');
        if ($root === '') {
            throw new \InvalidArgumentException('The scaffold root must be a non-empty path.');
        }
        if ($files === []) {
            throw new \InvalidArgumentException('A scaffold plan must contain at least one file.');
        }

        $paths = [];
        foreach ($files as $file) {
            if (isset($paths[$file->relativePath])) {
                throw new \InvalidArgumentException(\sprintf('Generated file path "%s" is duplicated.', $file->relativePath));
            }
            $paths[$file->relativePath] = true;
        }

        $this->root = $root;
        $this->files = $files;
        $this->notes = $notes;
    }
}
