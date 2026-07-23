<?php declare(strict_types=1);

namespace Frosh\Jetpack\CustomField;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Shopware\Core\Framework\Bundle;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 *
 * @phpstan-import-type FieldDefinition from CustomFieldDefinition
 * @phpstan-import-type SetDefinition from CustomFieldDefinition
 */
final class YamlCustomFieldLoader
{
    public const RELATIVE_PATH = '/Resources/config/custom-fields.yaml';

    private readonly object $schema;

    public function __construct()
    {
        $schemaPath = __DIR__ . '/../Resources/schema/custom-fields-1.json';
        $contents = file_get_contents($schemaPath);
        if ($contents === false) {
            throw new \RuntimeException(\sprintf('Cannot read Jetpack JSON schema "%s".', $schemaPath));
        }

        $this->schema = json_decode($contents, false, 512, \JSON_THROW_ON_ERROR);
    }

    public function load(Bundle $bundle): CustomFieldDefinition
    {
        $path = $bundle->getPath() . self::RELATIVE_PATH;
        if (!is_file($path)) {
            return new CustomFieldDefinition($path, []);
        }

        try {
            $document = Yaml::parseFile($path, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $exception) {
            throw CustomFieldException::invalidDefinition($path, $exception->getMessage());
        }

        if (!\is_array($document)) {
            throw CustomFieldException::invalidDefinition($path, 'The document must be a YAML mapping.');
        }

        $data = json_decode(json_encode($document, \JSON_THROW_ON_ERROR), false, 512, \JSON_THROW_ON_ERROR);
        $result = (new Validator())->validate($data, $this->schema);
        if (!$result->isValid()) {
            $error = $result->error();
            \assert($error !== null);
            $errors = (new ErrorFormatter())->format($error, false);

            throw CustomFieldException::invalidDefinition(
                $path,
                json_encode($errors, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
            );
        }

        return new CustomFieldDefinition($path, $this->normalize($document, $path));
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return list<SetDefinition>
     */
    private function normalize(array $document, string $path): array
    {
        $sets = [];
        $fieldNames = [];

        foreach ($document['sets'] as $setName => $set) {
            $fields = [];
            foreach ($set['fields'] as $fieldName => $field) {
                if (isset($fieldNames[$fieldName])) {
                    throw CustomFieldException::invalidDefinition(
                        $path,
                        \sprintf('Custom field name "%s" is duplicated across sets.', $fieldName),
                    );
                }
                $fieldNames[$fieldName] = true;

                $field['name'] = $fieldName;
                $field['helpText'] ??= [];
                $field['placeholder'] ??= [];
                $field['position'] ??= 1;
                $field['active'] ??= true;
                $field['required'] ??= false;
                $field['allowCustomerWrite'] ??= false;
                $field['allowCartExpose'] ??= false;
                $field['includeInSearch'] ??= false;
                $fields[] = $field;
            }

            $sets[] = [
                'name' => $setName,
                'label' => $set['label'],
                'global' => $set['global'] ?? false,
                'active' => $set['active'] ?? true,
                'position' => $set['position'] ?? 1,
                'relations' => array_values($set['relations']),
                'fields' => $fields,
            ];
        }

        return $sets;
    }
}
