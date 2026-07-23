<?php declare(strict_types=1);

namespace Frosh\Jetpack\MailTemplate;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Source;

/**
 * @internal
 *
 * @phpstan-import-type MailTemplateDeclaration from MailTemplateManifest
 * @phpstan-import-type MailTranslation from MailTemplateManifest
 */
final class YamlMailTemplateLoader
{
    public const RELATIVE_PATH = '/Resources/config/mail-templates.yaml';
    public const RELATIVE_CONTENT_PATH = '/Resources/mail-templates';

    private readonly object $schema;

    public function __construct(
        private readonly Environment $twig,
        private readonly DefinitionInstanceRegistry $definitions,
    ) {
        $schemaPath = __DIR__ . '/../Resources/schema/mail-templates-1.json';
        $contents = file_get_contents($schemaPath);
        if ($contents === false) {
            throw new \RuntimeException(\sprintf('Cannot read Jetpack JSON schema "%s".', $schemaPath));
        }

        $this->schema = json_decode($contents, false, 512, \JSON_THROW_ON_ERROR);
    }

    public function load(Bundle $bundle): MailTemplateManifest
    {
        $path = $bundle->getPath() . self::RELATIVE_PATH;
        if (!is_file($path)) {
            return new MailTemplateManifest($path, 'en-GB', []);
        }

        try {
            $document = Yaml::parseFile($path, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $exception) {
            throw MailTemplateException::invalidDefinition($path, $exception->getMessage());
        }

        if (!\is_array($document)) {
            throw MailTemplateException::invalidDefinition($path, 'The document must be a YAML mapping.');
        }

        $schemaDocument = $this->preserveEmptyMappings($document);
        $data = json_decode(json_encode($schemaDocument, \JSON_THROW_ON_ERROR), false, 512, \JSON_THROW_ON_ERROR);
        $result = (new Validator())->validate($data, $this->schema);
        if (!$result->isValid()) {
            $error = $result->error();
            \assert($error !== null);

            throw MailTemplateException::invalidDefinition(
                $path,
                json_encode(
                    (new ErrorFormatter())->format($error, false),
                    \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
                ),
            );
        }

        $defaultLocale = $document['default-locale'];
        if (!\is_string($defaultLocale)) {
            throw MailTemplateException::invalidDefinition($path, 'The default locale must be a string.');
        }

        return new MailTemplateManifest($path, $defaultLocale, $this->normalize($bundle, $document, $defaultLocale));
    }

    /**
     * Symfony YAML represents both an empty mapping and an empty sequence as an
     * empty PHP array. Restore the mapping semantics for fields whose schema
     * explicitly requires an object before converting the document to JSON.
     *
     * @param array<string, mixed> $document
     *
     * @return array<string, mixed>
     */
    private function preserveEmptyMappings(array $document): array
    {
        if (($document['templates'] ?? null) === []) {
            $document['templates'] = new \stdClass();

            return $document;
        }

        if (!\is_array($document['templates'] ?? null)) {
            return $document;
        }

        foreach ($document['templates'] as $technicalName => $template) {
            if (\is_array($template) && ($template['available-entities'] ?? null) === []) {
                $document['templates'][$technicalName]['available-entities'] = new \stdClass();
            }
        }

        return $document;
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return list<MailTemplateDeclaration>
     */
    private function normalize(Bundle $bundle, array $document, string $defaultLocale): array
    {
        $templates = [];
        foreach ($document['templates'] as $technicalName => $template) {
            if (!\is_string($technicalName) || !\is_array($template)) {
                throw MailTemplateException::invalidDefinition(
                    $bundle->getPath() . self::RELATIVE_PATH,
                    'Every template must be a YAML mapping with a technical-name key.',
                );
            }

            if (!isset($template['translations'][$defaultLocale])) {
                throw MailTemplateException::invalidDefinition(
                    $bundle->getPath() . self::RELATIVE_PATH,
                    \sprintf('Template "%s" must declare the default locale "%s".', $technicalName, $defaultLocale),
                );
            }

            $availableEntities = [];
            foreach ($template['available-entities'] as $variable => $entityName) {
                if (\is_string($entityName) && !$this->definitions->has($entityName)) {
                    throw MailTemplateException::invalidDefinition(
                        $bundle->getPath() . self::RELATIVE_PATH,
                        \sprintf('Template "%s" maps variable "%s" to unknown DAL entity "%s".', $technicalName, $variable, $entityName),
                    );
                }
                $availableEntities[$variable] = $entityName;
            }

            $translations = [];
            foreach ($template['translations'] as $locale => $translation) {
                if (!\is_string($locale) || !\is_array($translation)) {
                    throw MailTemplateException::invalidDefinition(
                        $bundle->getPath() . self::RELATIVE_PATH,
                        \sprintf('Template "%s" contains an invalid translation.', $technicalName),
                    );
                }

                $translations[$locale] = $this->translation($bundle, $technicalName, $locale, $translation);
            }

            $templates[] = [
                'technicalName' => $technicalName,
                'availableEntities' => $availableEntities,
                'updatePolicy' => $template['update-policy'] ?? 'preserve-user-changes',
                'translations' => $translations,
            ];
        }

        return $templates;
    }

    /**
     * @param array<string, mixed> $translation
     *
     * @return MailTranslation
     */
    private function translation(Bundle $bundle, string $technicalName, string $locale, array $translation): array
    {
        $root = $bundle->getPath() . self::RELATIVE_CONTENT_PATH;
        $contentHtml = $this->content($root, $technicalName, $locale, 'html.twig');
        $contentPlain = $this->content($root, $technicalName, $locale, 'txt.twig');

        return [
            'locale' => $locale,
            'name' => $translation['name'],
            'subject' => $translation['subject'],
            'senderName' => $translation['sender-name'] ?? null,
            'description' => $translation['description'] ?? null,
            'contentHtml' => $contentHtml,
            'contentPlain' => $contentPlain,
        ];
    }

    private function content(string $root, string $technicalName, string $locale, string $suffix): string
    {
        $path = $root . '/' . $technicalName . '/' . $locale . '.' . $suffix;
        $resolvedRoot = realpath($root);
        $resolvedPath = realpath($path);
        if ($resolvedRoot === false || $resolvedPath === false || !is_file($resolvedPath)) {
            throw MailTemplateException::invalidDefinition($path, 'The required Twig content file does not exist.');
        }
        if (!str_starts_with($resolvedPath, rtrim($resolvedRoot, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR)) {
            throw MailTemplateException::invalidDefinition($path, 'The Twig content file resolves outside Resources/mail-templates.');
        }

        $content = file_get_contents($resolvedPath);
        if ($content === false || trim($content) === '') {
            throw MailTemplateException::invalidDefinition($path, 'The Twig content file must not be empty.');
        }

        try {
            $this->twig->parse($this->twig->tokenize(new Source($content, $path)));
        } catch (SyntaxError $exception) {
            throw MailTemplateException::invalidDefinition($path, $exception->getMessage());
        }

        return $content;
    }
}
