<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

enum ApiScope: string
{
    case None = 'none';
    case Admin = 'admin';
    case Store = 'store';
    case All = 'all';
}
