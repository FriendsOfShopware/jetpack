<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

enum OnDelete: string
{
    case Cascade = 'CASCADE';
    case Restrict = 'RESTRICT';
    case SetNull = 'SET NULL';
    case NoAction = 'NO ACTION';
}
