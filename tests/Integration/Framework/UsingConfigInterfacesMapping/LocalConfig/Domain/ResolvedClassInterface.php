<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain;

abstract class ResolvedClassInterface
{
    abstract public function getTypes(): array;
}
