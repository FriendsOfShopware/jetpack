<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity;

use Frosh\Jetpack\Entity\Schema\EntitySchema;

interface JetpackDefinition
{
    public function getJetpackSchema(): EntitySchema;
}
