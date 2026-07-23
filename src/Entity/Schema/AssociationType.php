<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Schema;

enum AssociationType: string
{
    case ManyToOne = 'many-to-one';
    case OneToOne = 'one-to-one';
    case OneToMany = 'one-to-many';
    case ManyToMany = 'many-to-many';
}
