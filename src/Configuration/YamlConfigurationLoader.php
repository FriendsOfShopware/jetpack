<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
final class YamlConfigurationLoader
{
    private readonly object $schema;

    public function __construct(private readonly ValueTypeValidator $valueValidator)
    {
        $schemaPath = __DIR__ . '/../Resources/schema/configuration-1.json';
        $contents = file_get_contents($schemaPath);
        if ($contents === false) {
            throw new \RuntimeException(\sprintf('Cannot read Jetpack JSON schema "%s".', $schemaPath));
        }

        $this->schema = json_decode($contents, false, 512, \JSON_THROW_ON_ERROR);
    }

    public function load(BundleReference $bundle): ConfigurationDefinition
    {
        $path = $bundle->configurationPath();

        try {
            $document = Yaml::parseFile($path, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $exception) {
            throw ConfigurationException::invalidDefinition($path, $exception->getMessage());
        }

        if (!\is_array($document)) {
            throw ConfigurationException::invalidDefinition($path, 'The document must be a YAML mapping.');
        }

        $data = json_decode(json_encode($document, \JSON_THROW_ON_ERROR), false, 512, \JSON_THROW_ON_ERROR);
        $result = (new Validator())->validate($data, $this->schema);

        if (!$result->isValid()) {
            $error = $result->error();
            \assert($error !== null);
            $errors = (new ErrorFormatter())->format($error, false);
            throw ConfigurationException::invalidDefinition(
                $path,
                json_encode($errors, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
            );
        }

        return new ConfigurationDefinition($bundle, $this->fields($document, $path), $this->normalize($document));
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<string, FieldDefinition>
     */
    private function fields(array $document, string $path): array
    {
        $fields = [];

        foreach ($document['tabs'] as $tab) {
            foreach ($tab['sections'] as $section) {
                foreach ($section['fields'] as $key => $config) {
                    if (isset($fields[$key])) {
                        throw ConfigurationException::invalidDefinition($path, \sprintf('Field key "%s" is duplicated.', $key));
                    }

                    $field = new FieldDefinition(
                        $key,
                        $config['type'],
                        ConfigurationScope::from($config['scope'] ?? ConfigurationScope::Global->value),
                        $config['default'],
                        $config,
                    );
                    $this->valueValidator->validate($field, $field->defaultValue);
                    $fields[$key] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<string, mixed>
     */
    private function normalize(array $document): array
    {
        foreach ($document['tabs'] as &$tab) {
            $tab['position'] ??= 0;

            foreach ($tab['sections'] as &$section) {
                $section['position'] ??= 0;

                foreach ($section['fields'] as &$field) {
                    $field['scope'] ??= 'global';
                    $field['position'] ??= 0;
                    $field['nullable'] ??= false;
                }
                unset($field);
            }
            unset($section);
        }
        unset($tab);

        return $document;
    }
}
