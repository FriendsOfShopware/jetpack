<?php declare(strict_types=1);

namespace Frosh\Jetpack\Scaffolding;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
final class ScaffoldWriter
{
    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    public function write(ScaffoldPlan $plan, bool $dryRun): ScaffoldWriteResult
    {
        $this->validateContents($plan);

        $created = [];
        $unchanged = [];
        foreach ($plan->files as $file) {
            $this->assertNoSymlinkedDirectory($plan->root, $file->relativePath);
            $path = Path::join($plan->root, $file->relativePath);
            if (is_link($path)) {
                throw new \RuntimeException(\sprintf('Refusing to write symbolic link "%s".', $path));
            }
            if (!file_exists($path)) {
                $created[] = $path;

                continue;
            }
            if (!is_file($path)) {
                throw new \RuntimeException(\sprintf('Refusing to overwrite existing path "%s" because it is not a file.', $path));
            }

            $existing = file_get_contents($path);
            if ($existing === false) {
                throw new \RuntimeException(\sprintf('Cannot read existing file "%s".', $path));
            }
            if ($existing !== $file->content) {
                throw new \RuntimeException(\sprintf('Refusing to overwrite existing file "%s" because its content differs.', $path));
            }
            $unchanged[] = $path;
        }

        if ($dryRun) {
            return new ScaffoldWriteResult($created, $unchanged);
        }

        $written = [];
        try {
            foreach ($plan->files as $file) {
                $path = Path::join($plan->root, $file->relativePath);
                if (\in_array($path, $unchanged, true)) {
                    continue;
                }
                if (file_exists($path) || is_link($path)) {
                    throw new \RuntimeException(\sprintf('Generated file "%s" appeared after scaffold preflight.', $path));
                }
                $this->assertNoSymlinkedDirectory($plan->root, $file->relativePath);

                $this->filesystem->dumpFile($path, $file->content);
                $written[] = $path;
            }
        } catch (\Throwable $exception) {
            $this->filesystem->remove($written);

            throw $exception;
        }

        return new ScaffoldWriteResult($written, $unchanged);
    }

    private function validateContents(ScaffoldPlan $plan): void
    {
        foreach ($plan->files as $file) {
            if (!str_ends_with($file->relativePath, '.php')) {
                continue;
            }

            try {
                \PhpToken::tokenize($file->content, \TOKEN_PARSE);
            } catch (\ParseError $exception) {
                throw new \RuntimeException(\sprintf(
                    'Generated PHP file "%s" is invalid: %s',
                    $file->relativePath,
                    $exception->getMessage(),
                ), 0, $exception);
            }
        }
    }

    private function assertNoSymlinkedDirectory(string $root, string $relativePath): void
    {
        $directory = \dirname($relativePath);
        if ($directory === '.') {
            return;
        }

        $path = $root;
        foreach (explode('/', $directory) as $segment) {
            $path = Path::join($path, $segment);
            if (is_link($path)) {
                throw new \RuntimeException(\sprintf('Refusing to write through symbolic-link directory "%s".', $path));
            }
        }
    }
}
