<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

enum FieldType: string
{
    case Uuid = 'uuid';
    case String = 'string';
    case Text = 'text';
    case Int = 'int';
    case Float = 'float';
    case Bool = 'bool';
    case DateTime = 'datetime';
    case Date = 'date';
    case Json = 'json';
    case Blob = 'blob';
    case Email = 'email';
    case Price = 'price';
    case CustomFields = 'custom-fields';
    case Enum = 'enum';
    case Version = 'version';
    case ReferenceVersion = 'reference-version';
    case Custom = 'custom';
}
